<?php

namespace App\Http\Controllers;

use App\Repository\Plano\PlanoSelecionado\PlanoSelecionadoRepository;
use Illuminate\Http\Request;

class PlanoSelecionadoController extends Controller
{
    public function __construct(private PlanoSelecionadoRepository $planoSelecionadoRepository) {}

    /**
     * Qualquer usuário autenticado seleciona/troca seu próprio plano -
     * sem restrição de role, já que isto não é administração de planos
     * (isso continua em PlanosController, role:admin), é a escolha do
     * usuário para si mesmo.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name_plano' => ['required', 'string'],
        ]);

        if (! $this->planoSelecionadoRepository->VerificarPlanoSelecionado($data['name_plano'])) {
            return response()->json(['message' => 'Plano não encontrado.'], 404);
        }

        $selecionado = $this->planoSelecionadoRepository->gravarPlano($request->user()->id, $data['name_plano']);

        return response()->json($selecionado, 201);
    }
}
