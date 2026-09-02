<?php

namespace App\Interfaces;


interface PlanosInterface {
    public function cadastro($array);
    public function promocao($id,$valor);
    public function listar();
    public function buscar($id);
    public function deletar($id);
}
?>