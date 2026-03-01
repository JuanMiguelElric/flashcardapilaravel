<?php

namespace App\Repository\Plano\PlanoSelecionado;

use App\Interfaces\PlanoSelecionadoInterface;
use App\Models\Plano;
use App\Models\PlanoSelecionado;

class PlanoSelecionadoRepository implements PlanoSelecionadoInterface
{
    /**
     * Verificar se o plano selecionado existe.
     */
    public function VerificarPlanoSelecionado($planoSelecionado)
    {
        // Busca no banco de dados o plano com o nome informado
        $plano = Plano::where('name_plano', $planoSelecionado)->first();
        
        // Se o plano for encontrado, retorna true, caso contrário retorna false
        return $plano ? true : false;
    }

    /**
     * Gravar o plano selecionado para o usuário.
     */
    public function gravarPlano($user, $plano)
    {
        // Busca o plano no banco de dados com base no nome
        $planoSelecionado = Plano::where('name_plano', $plano)->first();
        
        // Se o plano não for encontrado, retorna um erro ou mensagem
        if (!$planoSelecionado) {
            return "Plano não encontrado.";
        }

        // Cria o registro no banco de dados para associar o plano ao usuário
        $data = [
            'id_usuario' => $user,
            'id_plano'   => $planoSelecionado->id
        ];

        $salvarPlano = PlanoSelecionado::create($data);
        
        // Retorna o plano salvo
        return $salvarPlano;
    }
}