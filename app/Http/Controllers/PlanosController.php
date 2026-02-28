<?php

namespace App\Http\Controllers;

use App\Models\Plano;
use App\Http\Controllers\Controller;
use App\Repository\PlanoRepository;
use Illuminate\Http\Request;

class PlanosController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function __contruct(PlanoRepository $planorepository){

      $this->planoRepository = $planorepository;

    }
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            "nome_plano"=>"required|string",
            "descricao"=> "required|string",
            "valor"=> "required|numeric",
        ]);

        return $this->planorepository->cadastro($data);
    }

    /**
     * Display the specified resource.
     */
    public function show(Plano $plano)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Plano $plano)
    {
        $data = $request->validate([
            "nome_plano"=>"required|string",
            "descricao"=>"required|string",
            "valor"=>"required|numeric",
            "desconto"=>"required|integer"
            ]);

            return $this->planoRepository->editarPlano($plano->id, $data);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Plano $plano)
    {
        //
    }
}
