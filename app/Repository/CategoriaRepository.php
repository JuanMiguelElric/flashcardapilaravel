<?php
namespace App\Repository;

use App\Interfaces\CategoriaInterface;
use App\Models\Categoria;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CategoriaRepository implements CategoriaInterface
{
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

        $categoria->delete();
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
