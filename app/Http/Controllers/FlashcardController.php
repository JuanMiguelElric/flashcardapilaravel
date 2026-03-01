<?php

namespace App\Http\Controllers;

use App\Models\Flashcard;
use App\Http\Controllers\Controller;
use App\Repository\Flashcard\FlashcardRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FlashcardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    protected $flashcardRepository;

     public function __construct(FlashcardRepository $flashCardRepository) {
        $this->flashcardRepository = $flashCardRepository;
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
            'categoria_id'=>"required|integer",
            "title"=>"required|string",
            "type"=>"nullable|integer",
            "description"=> "nullable|string",
            "alternatives"=>"nullable|string",
            "openRespostas"=>"nullable|string"
        ]);
        $dataformysql['categoria_id']= $data['categoria_id'];
        $dataformysql['user_id'] = Auth::user()->id;

        return $this->flashcardRepository->addFlashCard($dataformysql,$data);

    }

    /**
     * Display the specified resource.
     */
    public function show(Flashcard $flashcard)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Flashcard $flashcard)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Flashcard $flashcard)
    {
        //
    }
}
