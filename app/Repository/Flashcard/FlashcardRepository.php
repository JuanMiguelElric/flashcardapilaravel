<?php

namespace App\Repository\Flashcard;
use App\Interfaces\FlashcardInterface;
use App\Models\Flashcard;

class FlashcardRepository implements FlashcardInterface {
      public function addFlashCard( $flashCard,$flashcardForGrafo){
        //first add in mysql

        
        $flashcardTable = Flashcard::where("categoria_id",$flashCard["categoria_id"])->where("user_id",$flashCard["user_id"])->first();

        if(empty($flashcardTable)){
            $data = $flashCard;
            $data["count_flashcard_register"]= 1;
              $cards = new Flashcard($data);
              if($cards->save()){
                  return response()->json(["sucess"=>"cadastrado com sucesso"] ,201);
              }
      
        }else{}
        dd($flashcardTable);




      }
      public function removeFlashCard( $flashCard){

      }
}