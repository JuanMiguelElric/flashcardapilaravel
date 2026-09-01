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
    public function __construct(private FlashcardServiceClient $client)
    {
    }

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
     * Associação por flashcard_id (ideal) só é possível quando o Python
     * devolve esse campo. Enquanto isso não existir do lado do Python,
     * usamos um fallback best-effort (consome flashcard_items da mesma
     * categoria em ordem) - ver relatório de gaps.
     */
    private function mergeContent($items, array $rawGroups, int $userId): array
    {
        $itemsByCategoria = $items->groupBy('categoria_id');
        $categoriaIdByName = $items->pluck('categoria.id', 'categoria.nome_categoria');

        $result = [];
        $seen = [];
        $consumedItemIds = [];

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

                if ($flashcardId === null && $categoriaId !== null) {
                    $candidate = ($itemsByCategoria[$categoriaId] ?? collect())
                        ->first(fn ($item) => ! in_array($item->id, $consumedItemIds, true));

                    if ($candidate) {
                        $flashcardId = $candidate->id;
                        $consumedItemIds[] = $flashcardId;
                    }
                }

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
