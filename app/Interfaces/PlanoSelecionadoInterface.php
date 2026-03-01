<?php

namespace App\Interfaces;

interface PlanoSelecionadoInterface{
    public function VerificarPlanoSelecionado($planoSelecionado);
    public function gravarPlano($user,$plano);
}