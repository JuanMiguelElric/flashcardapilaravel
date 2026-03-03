<?php
namespace App\Repository;

use App\Interfaces\CategoriaInterface;
use App\Models\Categoria;

class CategoriaRepository implements CategoriaInterface{
    // apenas apra cointeúdos
    public function categoriaIndexCriados()
    {
        $categoria = Categoria::all();
        return $categoria;
    }
    public function categoriaDeCriarConteudo($data)
    {
        
        $dataforepo = $data;
       
        $categoria = new Categoria($dataforepo);
        if($categoria->save()){
            
                    return response()->json([
                        "success" => "Salvo com sucesso",
                        "data" => $categoria
                    ], 201);
            
        }else{
            dd("foi não");
        }
    }


    public function categoriaDeVenda()
    {
        throw new \Exception('Not implemented');
    }

 
}