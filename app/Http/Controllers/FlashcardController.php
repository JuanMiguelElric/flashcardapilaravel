<?php

namespace App\Http\Controllers;

use App\Models\Flashcard;
use App\Http\Controllers\Controller;
use App\Repository\Flashcard\FlashcardRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


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
        $user = Auth::user();   
        return $this->flashcardRepository->RetornarFlashcardDecadaUsuario($user->id);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        Log::info('Requisição recebida:', $request->all());
        $data = $request->validate([
     
            'categoryId'=>"nullable|integer",
            //fronteend é question backeend está como title
            "question"=>"nullable|string",
            "type"=>"nullable|string",
            //fronteend é content aqui no back é description
            "content"=> "nullable|string",
            // fronteend é options que é multipla escolha
           // "options"=>"nullable|string",
            "answer"=>"nullable|string"
        ]);
        Log::info("recebeu até aqui", $data);
        $dataformysql['categoria_id']= $data['categoryId'];
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
