<?php
namespace App\Repository;

use App\Interfaces\CategoriaInterface;
use App\Models\Categoria;
use Illuminate\Support\Facades\Auth;

class CategoriaRepository implements CategoriaInterface{
    // apenas apra cointeúdos
    public function categoriaIndexCriados()
    {
        $categoria = Categoria::all();
        return $categoria;
    }
    public function categoriaDeCriarConteudo($data)
    {
        $data['user_id'] = Auth::id();

        $categoria = Categoria::create($data);

        return response()->json([
            "success" => "Salvo com sucesso",
            "data" => $categoria
        ], 201);
    }


    public function categoriaDeVenda()
    {
        throw new \Exception('Not implemented');
    }

 
}