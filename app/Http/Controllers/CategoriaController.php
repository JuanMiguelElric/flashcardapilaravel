<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;
use App\Repository\CategoriaRepository;

class CategoriaController extends Controller
{
     protected $categoriaRepository;


     public function __construct(CategoriaRepository $categoriaRepository)
     {
        $this->categoriaRepository = $categoriaRepository;
     }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return $this->categoriaRepository->categoriaIndexCriados();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $data = $request->validate([
            'nome_categoria'=>"required|string"
        ]);
        return $this->categoriaRepository
                    ->categoriaDeCriarConteudo($data["nome_categoria"]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Categoria $categoria)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Categoria $categoria)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Categoria $categoria)
    {
        //
    }
}
