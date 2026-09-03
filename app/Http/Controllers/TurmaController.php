<?php

namespace App\Http\Controllers;

use App\Models\Instituicao;
use App\Models\Turma;
use App\Services\InstituicaoService;
use Illuminate\Http\Request;

class TurmaController extends Controller
{
    public function __construct(private InstituicaoService $instituicaoService) {}

    /**
     * Turmas que o usuário autenticado leciona - não usa InstituicaoService
     * (é só leitura do próprio usuário, sem checagem de ownership de
     * terceiros necessária).
     */
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->turmasQueLeciona()->withCount('alunosAtivos')->get()
        );
    }

    public function store(Request $request, Instituicao $instituicao)
    {
        $data = $request->validate(['nome' => ['required', 'string', 'max:255']]);

        $turma = $this->instituicaoService->criarTurma($request->user(), $instituicao, $data['nome']);

        return response()->json($turma, 201);
    }

    public function convidarAluno(Request $request, Turma $turma)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $convite = $this->instituicaoService->convidarAluno($turma, $request->user(), $data['email']);

        return response()->json($convite, 201);
    }

    public function alunos(Request $request, Turma $turma)
    {
        return response()->json($this->instituicaoService->listarAlunos($turma, $request->user()));
    }
}
