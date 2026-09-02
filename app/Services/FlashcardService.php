<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\FlashcardItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Orquestra a criação/leitura/atualização/remoção de flashcards:
 * garante ownership, persiste a identidade canônica no MySQL
 * (flashcard_items) e delega o conteúdo ao microsserviço Python via
 * FlashcardServiceClient. Nenhum outro lugar do código deve montar o
 * payload enviado ao Python ou decidir quem é dono de um flashcard.
 */
class FlashcardService
{
    public function __construct(private FlashcardServiceClient $client) {}

    public function create(User $user, array $data): array
    {
        $categoria = $this->ownedCategoria($user, $data['categoryId']);

        return DB::transaction(function () use ($user, $categoria, $data) {
            $item = FlashcardItem::create([
                'user_id' => $user->id,
                'categoria_id' => $categoria->id,
                'type' => $data['type'],
            ]);

            $this->client->submitFlashcard(
                $item->id,
                $this->buildContentPayload($categoria, $user, $data, $data['type'])
            );

            return $this->present($item, $categoria, $data);
        });
    }

    public function listForUser(User $user): array
    {
        $items = FlashcardItem::with('categoria')->where('user_id', $user->id)->get();

        if ($items->isEmpty()) {
            return [];
        }

        $raw = $this->client->fetchIndexForUser($user->id);

        return $this->mergeContent($items, $raw, $user->id);
    }

    public function update(FlashcardItem $item, User $user, array $data): array
    {
        $this->authorizeOwnership($item, $user);

        $categoria = isset($data['categoryId'])
            ? $this->ownedCategoria($user, $data['categoryId'])
            : $item->categoria;

        return DB::transaction(function () use ($item, $categoria, $user, $data) {
            $item->categoria_id = $categoria->id;
            $item->type = $data['type'] ?? $item->type;
            $item->save();

            // $data['type'] pode estar ausente num update parcial - usar
            // sempre $item->type (já resolvido acima) para decidir qual
            // campo type-specific vai no payload, nunca recalcular a
            // partir de $data isoladamente.
            $this->client->updateFlashcard(
                $item->id,
                $this->buildContentPayload($categoria, $user, $data, $item->type)
            );

            return $this->present($item, $categoria, $data);
        });
    }

    public function delete(FlashcardItem $item, User $user): void
    {
        $this->authorizeOwnership($item, $user);

        DB::transaction(function () use ($item, $user) {
            $this->client->deleteFlashcard($item->id, $user->id);
            $item->delete();
        });
    }

    /**
     * Remove no Python/Neo4j o conteúdo de todos os flashcards de uma
     * categoria - deve ser chamado ANTES da categoria ser excluída no
     * MySQL, pelo mesmo motivo do delete() acima: flashcard_items.
     * categoria_id tem cascadeOnDelete() (ver migration
     * 2026_08_31_120000_create_flashcard_items_table.php), então excluir
     * a categoria apagaria os flashcard_items em cascata no MySQL sem
     * nunca avisar o Python - deixando nodes :flashcard órfãos no Neo4j.
     *
     * O chamador (CategoriaRepository::categoriaDeExcluir) deve envolver
     * esta chamada e o $categoria->delete() na MESMA DB::transaction: se
     * qualquer delete no Python falhar, a exceção propaga e nada é
     * apagado no MySQL (nem a categoria, nem os flashcard_items).
     *
     * Limitação estrutural documentada: MySQL e Neo4j não compartilham
     * uma transação distribuída real. Numa categoria com múltiplos
     * flashcards, se o Python confirmar a remoção do item N e só falhar
     * no item N+1 (após esgotar o retry de FlashcardServiceClient), o
     * MySQL desta transação é revertido por inteiro (nada é apagado),
     * mas o node do item N já foi removido do Neo4j - o flashcard_item N
     * continua existindo no MySQL sem conteúdo correspondente no Neo4j.
     * O retry embutido em FlashcardServiceClient::send() reduz bastante a
     * chance disso (cada chamada já tenta se recuperar sozinha antes de
     * desistir), mas não a elimina - resolver por completo exigiria uma
     * transação distribuída real (saga/compensação) fora do escopo desta
     * correção mínima.
     */
    public function deleteAllForCategoria(Categoria $categoria, User $user): void
    {
        $items = FlashcardItem::where('categoria_id', $categoria->id)
            ->where('user_id', $user->id)
            ->get();

        foreach ($items as $item) {
            $this->client->deleteFlashcard($item->id, $user->id);
        }
    }

    private function authorizeOwnership(FlashcardItem $item, User $user): void
    {
        if ((int) $item->user_id !== (int) $user->id) {
            throw new HttpException(403, 'Você não tem permissão para acessar este flashcard.');
        }
    }

    private function ownedCategoria(User $user, mixed $categoriaId): Categoria
    {
        $categoria = Categoria::where('id', $categoriaId)->where('user_id', $user->id)->first();

        if (! $categoria) {
            throw ValidationException::withMessages([
                'categoryId' => 'Categoria inválida ou não pertence a este usuário.',
            ]);
        }

        return $categoria;
    }

    /**
     * NOTA DE MODELAGEM (não sincronizado - decisão a confirmar com negócio):
     * Categoria aqui é a entidade MySQL (id, user_id, nome_categoria).
     * O Python/Neo4j não conhece esse id - ele recebe só o NOME da
     * categoria e faz MERGE num node (:categoria) global, compartilhado
     * entre todos os usuários. São entidades desacopladas: renomear uma
     * Categoria no MySQL não atualiza o node (:categoria) já criado no
     * Neo4j com o nome antigo (os flashcards antigos continuam agrupados
     * sob o nome anterior). Nenhuma chamada ao Python existe em
     * CategoriaController/CategoriaRepository. Não implementar
     * sincronização sem requisito de negócio explícito.
     */
    private function buildContentPayload(Categoria $categoria, User $user, array $data, string $type): array
    {
        return [
            'categoria' => $categoria->nome_categoria,
            'tipo' => (string) $type,
            'usuario' => $user->id,
            'flashcard' => [
                'question' => $data['question'] ?? null,
                'summary' => $type === 'summary' ? ($data['content'] ?? null) : null,
                'answer' => $type === 'open-ended' ? ($data['answer'] ?? null) : null,
                'options' => $type === 'multiple-choice' ? ($data['options'] ?? []) : null,
                'translation' => $type === 'audio' ? ($data['translation'] ?? null) : null,
                'audioUrl' => $type === 'audio' ? ($data['audioUrl'] ?? null) : null,
            ],
        ];
    }

    private function present(FlashcardItem $item, Categoria $categoria, array $data): array
    {
        return [
            'id' => $item->id,
            'categoryId' => $categoria->id,
            'type' => $item->type,
            'question' => $data['question'] ?? null,
            'content' => $data['content'] ?? null,
            'answer' => $data['answer'] ?? null,
            'options' => $data['options'] ?? null,
            'translation' => $data['translation'] ?? null,
            'audioUrl' => $data['audioUrl'] ?? null,
        ];
    }

    /**
     * Reconcilia a resposta do Python com os flashcard_items locais.
     *
     * Defesa em profundidade: mesmo que o Python devolva dados de outro
     * usuário (filtro quebrado, bug, etc.), só entram no resultado grupos
     * cujo campo "usuario" bate com o usuário autenticado - nunca um
     * valor vindo do cliente.
     *
     * A associação de cada flashcard ao seu ID canônico usa sempre
     * flashcard_id, devolvido pelo Python em GET /flashcard/index
     * (confirmado em flashcard_repository.py::list_for_user, campo
     * incluso em toda entrada de "flashcards"). Um fallback posicional
     * best-effort existiu aqui até esta revisão para o caso do Python não
     * devolver esse campo; foi removido por ser código morto - reintroduza
     * apenas se o contrato do Python mudar para omitir flashcard_id.
     */
    private function mergeContent($items, array $rawGroups, int $userId): array
    {
        $categoriaIdByName = $items->pluck('categoria.id', 'categoria.nome_categoria');

        $result = [];
        $seen = [];

        foreach ($rawGroups as $grupo) {
            if (! is_array($grupo) || (string) ($grupo['usuario'] ?? null) !== (string) $userId) {
                continue;
            }

            if (! isset($grupo['flashcards']) || ! is_array($grupo['flashcards'])) {
                continue;
            }

            $categoriaId = $categoriaIdByName[$grupo['categoria'] ?? null] ?? null;

            foreach ($grupo['flashcards'] as $flashcard) {
                $key = md5(($grupo['categoria'] ?? '').($flashcard['question'] ?? '').($flashcard['summary'] ?? ''));

                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $flashcardId = $flashcard['flashcard_id'] ?? null;

                $options = json_decode($flashcard['multiple_choice'] ?? 'null', true);

                $result[] = [
                    'id' => $flashcardId,
                    'categoryId' => $categoriaId,
                    'question' => $flashcard['question'] ?? '',
                    'type' => $grupo['tipo'] ?? '',
                    'content' => $flashcard['summary'] ?? '',
                    'options' => $options ?? [],
                    'answer' => $flashcard['answer'] ?? '',
                    'translation' => $flashcard['translation'] ?? null,
                    'audioUrl' => $flashcard['audioUrl'] ?? null,
                ];
            }
        }

        return $result;
    }
}
