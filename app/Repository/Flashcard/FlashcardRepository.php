<?php

namespace App\Repository\Flashcard;
use App\Interfaces\FlashcardInterface;
use App\Models\Categoria;
use App\Models\Flashcard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
class FlashcardRepository implements FlashcardInterface {
      public function addFlashCard( $flashCard,$flashcardForGrafo){
        //first add in mysql

        $flashcardTable = Flashcard::where("categoria_id",(int)$flashCard["categoria_id"])->where("user_id",$flashCard["user_id"])->first();
      

        if(empty($flashcardTable)){
            $data = $flashCard;
            $data["count_flashcard_register"]= 1;
              $cards = new Flashcard($data);
              if($cards->save()){
                  $this->createFlashcard($flashcardForGrafo);
                  return response()->json(["sucess"=>"cadastrado com sucesso"] ,201);
              }
      
        }else{
          $data = $flashCard;
        $data["count_flashcard_register"] = $flashcardTable->count_flashcard_register + 1;
        // Atualiza o flashcard existente
        $card = Flashcard::where("categoria_id", (int)$flashCard["categoria_id"])
        ->where("user_id", $flashCard["user_id"])
        ->first();

        if ($card) {
            if ($card->update($data)) {
                   $this->createFlashcard($flashcardForGrafo);
                return response()->json(["success" => "Flashcard atualizado com sucesso"], 200);
            } else {
                return response()->json(["error" => "Erro ao atualizar flashcard"], 500);
            }
        } else {
            return response()->json(["error" => "Flashcard não encontrado"], 404);
        }
        }
        


      }
      public function removeFlashCard( $flashCard){

      }

    protected function createFlashcard($flashCard)
    {   
        $user = Auth::user()->id;

        $categoria = Categoria::where("id", (int)$flashCard["categoryId"])->first();

        $flashcardarray = [
            "question" => $flashCard["question"],
            "summary"=> ($flashCard["type"] == "summary") ? $flashCard["content"] : null,
            "open-ended"=> ($flashCard["type"] == "open-ended") ? $flashCard["open-ended"] : null,
            "multiple-choice"=> ($flashCard["type"] == "multiple-choice") ? $flashCard["options"]:null,
        ];

    
        $data = [
            "categoria" => $categoria->nome_categoria,
            "tipo" => $flashCard["type"]."",
            "flashcard" => $flashcardarray,
            "usuario" => $user
        ];
    
        $url = "http://127.0.0.1:5000/submit_flash";

        try {
            $response = Http::timeout(10)
                            ->withHeaders([
                                'Content-Type' => 'application/json',  // Certifique-se de que está enviando JSON
                            ])
                            ->post($url, $data);  // Envia diretamente o array $data

            if ($response->successful()) {
                return response()->json($response->json(), 200); // Retorna o conteúdo da resposta
            } else {
                return response()->json(['error' => 'Erro na requisição'], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function RetornarFlashcardDecadaUsuario($usuario)
    {
        $url = "http://127.0.0.1:5000/flashcard/index";

        $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',  // Certifique-se de que está enviando JSON
                ])
                ->get($url);
        $data =[];

        foreach($response->json() as $flashCard) {
            if($usuario == $flashCard['usuario']) {
                //$categoria = Categoria::find($flashCard['categoria']);
                $data['user']=[
                    "categoria"=>$flashCard['categoria']['nome'],
                    "question"=>(isset($flashCard["flashcard"]["titulo"])) ? $flashCard["flashcard"]["titulo"] : null ,
                    "content"=>(isset($flashCard["flashcard"]["descricao"])) ? $flashCard["flashcard"]["descricao"]: null,
                
                ];
            }

        }
        return response()->json($data);
    }


}