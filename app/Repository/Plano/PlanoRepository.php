<?php 

namespace App\Repository\Plano;
use  App\Interfaces\PlanosInterface; 
use App\Models\Plano;
class PlanoRepository implements PlanosInterface{
    public function cadastro($array)
    {
        $plano = new Plano($array);
        if($plano->save()){
            return response()->json(["success"=> true,"plano criado com sucesso "=> $plano->id],201);
        }
    }
    public function promocao($id, $valor)
    {
        $plano = Plano::where("id", $id)->first();
        if($plano->update($valor)){
            return response()->json(["success"=> true,"Plano editado com sucesso "=> $plano->id],201);
        }

    }



}


?>