<?php
namespace App\Interfaces;

use App\Models\Categoria;
use App\Models\User;

interface CategoriaInterface
{
    public function categoriaDeVenda();

    public function categoriaDeCriarConteudo(array $data, User $user);

    public function categoriaIndexCriados(User $user);

    public function categoriaDeAtualizar(Categoria $categoria, User $user, array $data): Categoria;

    public function categoriaDeExcluir(Categoria $categoria, User $user): void;

    public function categoriaDeMostrar(Categoria $categoria, User $user): Categoria;
}
