<?php

namespace App\Http\Controllers;

use App\Models\Instituicao;
use App\Services\InstituicaoService;
use Illuminate\Http\Request;

class InstituicaoController extends Controller
{
    public function __construct(private InstituicaoService $instituicaoService) {}

    /**
     * Instituições onde o usuário autenticado é professor ativo (inclui
     * o próprio dono, que já nasce professor ativo - ver
     * InstituicaoService::criar). Só leitura do próprio vínculo, sem
     * checagem de ownership de terceiros necessária.
     */
    public function index(Request $request)
    {
        $instituicoes = Instituicao::whereHas('professoresAtivos', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->get();

        return response()->json($instituicoes);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['nome' => ['required', 'string', 'max:255']]);

        $instituicao = $this->instituicaoService->criar($request->user(), $data['nome']);

        return response()->json($instituicao, 201);
    }

    public function convidarProfessor(Request $request, Instituicao $instituicao)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $convite = $this->instituicaoService->convidarProfessor($instituicao, $request->user(), $data['email']);

        return response()->json($convite, 201);
    }
}
