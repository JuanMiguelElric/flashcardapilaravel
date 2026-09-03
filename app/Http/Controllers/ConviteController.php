<?php

namespace App\Http\Controllers;

use App\Models\InstituicaoProfessor;
use App\Models\TurmaAluno;
use App\Services\InstituicaoService;
use Illuminate\Http\Request;

class ConviteController extends Controller
{
    public function __construct(private InstituicaoService $instituicaoService) {}

    public function aceitarProfessor(Request $request, InstituicaoProfessor $convite)
    {
        $aceito = $this->instituicaoService->aceitarConviteProfessor($convite, $request->user());

        return response()->json($aceito);
    }

    public function aceitarAluno(Request $request, TurmaAluno $convite)
    {
        $aceito = $this->instituicaoService->aceitarConviteAluno($convite, $request->user());

        return response()->json($aceito);
    }
}
