<?php

namespace App\Http\Controllers;

use App\Models\Turma;
use App\Models\User;
use App\Services\InstituicaoService;
use Illuminate\Http\Request;

class RelatorioController extends Controller
{
    public function __construct(private InstituicaoService $instituicaoService) {}

    public function doAluno(Request $request, Turma $turma, User $aluno)
    {
        $relatorio = $this->instituicaoService->relatorioDoAluno($turma, $request->user(), $aluno);

        return response()->json($relatorio);
    }
}
