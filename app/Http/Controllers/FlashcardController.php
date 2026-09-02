<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFlashcardRequest;
use App\Http\Requests\UpdateFlashcardRequest;
use App\Models\FlashcardItem;
use App\Services\FlashcardService;
use Illuminate\Http\Request;

class FlashcardController extends Controller
{
    /**
     * Padrão de paginação para GET /flashcard/index - não inferível de
     * nenhum padrão existente no projeto (nenhum outro endpoint pagina
     * hoje), então documentado aqui como decisão de engenharia razoável,
     * não uma regra de negócio.
     */
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    public function __construct(private FlashcardService $flashcardService)
    {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? self::DEFAULT_PER_PAGE;

        return response()->json($this->flashcardService->listForUser($request->user(), $page, $perPage));
    }

    public function store(StoreFlashcardRequest $request)
    {
        $flashcard = $this->flashcardService->create($request->user(), $request->validated());

        return response()->json($flashcard, 201);
    }

    public function update(UpdateFlashcardRequest $request, FlashcardItem $flashcard)
    {
        $updated = $this->flashcardService->update($flashcard, $request->user(), $request->validated());

        return response()->json($updated);
    }

    public function destroy(Request $request, FlashcardItem $flashcard)
    {
        $this->flashcardService->delete($flashcard, $request->user());

        return response()->json(null, 204);
    }
}
