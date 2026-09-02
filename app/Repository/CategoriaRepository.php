<?php

namespace App\Repository;

use App\Interfaces\CategoriaInterface;
use App\Models\Categoria;
use App\Models\User;
use App\Services\FlashcardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CategoriaRepository implements CategoriaInterface
{
    public function __construct(private FlashcardService $flashcardService) {}

    public function categoriaIndexCriados(User $user)
    {
        return Categoria::where('user_id', $user->id)->get();
    }

    public function categoriaDeCriarConteudo(array $data, User $user)
    {
        $categoria = new Categoria(array_merge($data, ['user_id' => $user->id]));

        if (! $categoria->save()) {
            Log::error('Falha ao salvar categoria', ['user_id' => $user->id]);

            throw new HttpException(500, 'Não foi possível salvar a categoria.');
        }

        return $categoria;
    }

    public function categoriaDeAtualizar(Categoria $categoria, User $user, array $data): Categoria
    {
        $this->authorizeOwnership($categoria, $user);

        $categoria->fill($data);
        $categoria->save();

        return $categoria;
    }

    public function categoriaDeExcluir(Categoria $categoria, User $user): void
    {
        $this->authorizeOwnership($categoria, $user);

        // flashcard_items.categoria_id tem cascadeOnDelete() no MySQL -
        // sem remover primeiro o conteúdo no Python/Neo4j, o cascade
        // apagaria os flashcard_items localmente sem nunca avisar o
        // Python, deixando nodes :flashcard órfãos no Neo4j. Ver
        // FlashcardService::deleteAllForCategoria para a limitação
        // estrutural documentada (MySQL/Neo4j não têm transação
        // distribuída real).
        DB::transaction(function () use ($categoria, $user) {
            $this->flashcardService->deleteAllForCategoria($categoria, $user);
            $categoria->delete();
        });
    }

    public function categoriaDeMostrar(Categoria $categoria, User $user): Categoria
    {
        $this->authorizeOwnership($categoria, $user);

        return $categoria;
    }

    private function authorizeOwnership(Categoria $categoria, User $user): void
    {
        if ((int) $categoria->user_id !== (int) $user->id) {
            throw new HttpException(403, 'Você não tem permissão para acessar esta categoria.');
        }
    }

    public function categoriaDeVenda()
    {
        throw new \Exception('Not implemented');
    }
}
