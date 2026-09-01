<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoriaRequest;
use App\Http\Requests\UpdateCategoriaRequest;
use App\Models\Categoria;
use App\Repository\CategoriaRepository;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function __construct(private CategoriaRepository $categoriaRepository)
    {
    }

    public function index(Request $request)
    {
        return response()->json($this->categoriaRepository->categoriaIndexCriados($request->user()));
    }

    public function store(StoreCategoriaRequest $request)
    {
        $categoria = $this->categoriaRepository->categoriaDeCriarConteudo(
            $request->validated(),
            $request->user()
        );

        return response()->json($categoria, 201);
    }

    public function show(Request $request, Categoria $categoria)
    {
        return response()->json(
            $this->categoriaRepository->categoriaDeMostrar($categoria, $request->user())
        );
    }

    public function update(UpdateCategoriaRequest $request, Categoria $categoria)
    {
        $updated = $this->categoriaRepository->categoriaDeAtualizar(
            $categoria,
            $request->user(),
            $request->validated()
        );

        return response()->json($updated);
    }

    public function destroy(Request $request, Categoria $categoria)
    {
        $this->categoriaRepository->categoriaDeExcluir($categoria, $request->user());

        return response()->json(null, 204);
    }
}
