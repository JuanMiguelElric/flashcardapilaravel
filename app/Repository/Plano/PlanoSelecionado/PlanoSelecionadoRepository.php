<?php

namespace App\Repository\Plano\PlanoSelecionado;

use App\Interfaces\PlanoSelecionadoInterface;
use App\Models\Plano;
use App\Models\PlanoSelecionado;
use Illuminate\Support\Facades\DB;

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
     * Gravar o plano selecionado para o usuário - desativa qualquer
     * plano_selecionado ativo anterior do mesmo usuário antes de criar o
     * novo, para garantir no máximo um plano ativo por usuário
     * (User::planoAtivo() depende disso).
     */
    public function gravarPlano($user, $plano)
    {
        // Busca o plano no banco de dados com base no nome
        $planoSelecionado = Plano::where('name_plano', $plano)->first();

        // Se o plano não for encontrado, retorna um erro ou mensagem
        if (!$planoSelecionado) {
            return "Plano não encontrado.";
        }

        return DB::transaction(function () use ($user, $planoSelecionado) {
            PlanoSelecionado::where('id_usuario', $user)
                ->where('status', 1)
                ->update(['status' => 0]);

            return PlanoSelecionado::create([
                'id_usuario' => $user,
                'id_plano' => $planoSelecionado->id,
                'status' => 1,
            ]);
        });
    }
}