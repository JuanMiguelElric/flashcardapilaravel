<?php
namespace App\Repository;

use App\Interfaces\CategoriaInterface;
use App\Models\Categoria;

class CategoriaRepository implements CategoriaInterface{
    public function categoriaDeCriarConteudo($data)
    {
        $categoria = new Categoria();

        if($categoria->save()){
            return response()->json(["success"=>"Salvo com sucesso "],201);
        }else{
            return response()->json(["error"=>"Não foi Salva"],500);
        }
        
    }

    public function categoriaDeVenda()
    {
        throw new \Exception('Not implemented');
    }

 
}