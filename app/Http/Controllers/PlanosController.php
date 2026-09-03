<?php

namespace App\Http\Controllers;

use App\Models\Plano;
use App\Http\Controllers\Controller;
use App\Repository\Plano\PlanoRepository;
use Illuminate\Http\Request;

class PlanosController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function __construct(PlanoRepository $planorepository){

      $this->planoRepository = $planorepository;

    }
    public function index()
    {
        return response()->json($this->planoRepository->listar());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            "name_plano"=>"required|string",
            "Descricao"=> "required|string",
            "valor"=> "required|numeric",
            "desconto"=>"required|integer",
            // Sem valor padrão de negócio definido - null = sem limite
            // aplicado (ver PlanLimitService). Opcional propositalmente.
            "limite_flashcards"=>"nullable|integer|min:0",
            "limite_categorias"=>"nullable|integer|min:0",
            "permite_audio"=>"sometimes|boolean",
            "permite_multipla_escolha"=>"sometimes|boolean",
            "estatisticas_avancadas"=>"sometimes|boolean",
            "max_alunos"=>"nullable|integer|min:0",
        ]);

        return $this->planoRepository->cadastro($data);
    }

    /**
     * Display the specified resource.
     */
    public function show(Plano $plano)
    {
        return response()->json($this->planoRepository->buscar($plano->id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Plano $plano)
    {
        $data = $request->validate([
            "name_plano"=>"required|string",
            "Descricao"=>"required|string",
            "valor"=>"required|numeric",
            "desconto"=>"required|integer",
            "limite_flashcards"=>"nullable|integer|min:0",
            "limite_categorias"=>"nullable|integer|min:0",
            "permite_audio"=>"sometimes|boolean",
            "permite_multipla_escolha"=>"sometimes|boolean",
            "estatisticas_avancadas"=>"sometimes|boolean",
            "max_alunos"=>"nullable|integer|min:0",
            ]);

            return $this->planoRepository->editarPlano($plano->id, $data);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Plano $plano)
    {
        $this->planoRepository->deletar($plano->id);

        return response()->json(null, 204);
    }
}
