<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFlashcardRequest;
use App\Http\Requests\UpdateFlashcardRequest;
use App\Models\FlashcardItem;
use App\Services\FlashcardService;
use Illuminate\Http\Request;

class FlashcardController extends Controller
{
    public function __construct(private FlashcardService $flashcardService)
    {
    }

    public function index(Request $request)
    {
        return response()->json($this->flashcardService->listForUser($request->user()));
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
