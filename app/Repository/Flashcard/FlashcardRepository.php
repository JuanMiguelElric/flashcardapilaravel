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
            "answer"=> ($flashCard["type"] == "open-ended") ? $flashCard["open-ended"] : null,
            "options"=> ($flashCard["type"] == "multiple-choice") ? $flashCard["options"]:null,
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
                            'Content-Type' => 'application/json',
                        ])
                        ->get($url);
        
      //  dd($response->json());
        
        $data = []; // Array para armazenar os flashcards encontrados

        // Iterar sobre todos os flashcards retornados
        foreach ($response->json() as $flashCard) {
            $categoria = Categoria::where('nome_categoria',$flashCard['categoria'])->first();
            // Verifica se o 'usuario' do flashcard corresponde ao usuário solicitado
                if ($usuario == $flashCard['usuario']) {

                    // Verifica se o campo 'flash' existe e não está vazio
                        // Decodifica o campo 'flash' (que é uma string JSON)
                        
                        // Verifica se a decodificação foi bem-sucedida
              
                            // Adiciona o flashcard encontrado ao array de resultados
                            $data[] = [
                                'categoryId'=> $categoria->id,
                                "question" => $flashCard['flashcard']['question'], // Defina um valor padrão caso 'titulo' não exista
                                "type"=>$flashCard["tipo"],
                                "content" => $flashCard['flashcard']['summary'] ?? "", // Defina um valor padrão caso 'descricao' não exista
                                "options"=> $flashCardData['flashcard']["multiple-choice"] ?? [],
                              //  "answer"=> $flashCard[""][""] ??"",
                            ];
                        
                    
                }
            
        }

        // Retorna os dados encontrados ou uma mensagem de erro caso não haja flashcards

        return response()->json($data, 200);
    }


}