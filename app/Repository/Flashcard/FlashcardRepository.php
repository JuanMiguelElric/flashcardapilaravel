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

    // 🔥 Validação da API
    if (!$response->successful()) {
        return response()->json(['erro' => 'Erro ao consumir API'], 500);
    }

    $json = $response->json();

    if (!is_array($json)) {
        return response()->json(['erro' => 'Resposta inválida da API'], 500);
    }

    $data = [];
    $seen = [];

    // 🔥 Evita N+1 (muito importante)
    $categorias = Categoria::pluck('id', 'nome_categoria');

    foreach ($json as $grupo) {

        if (($grupo['usuario'] ?? null) != $usuario) {
            continue;
        }

        if (!isset($grupo['flashcards']) || !is_array($grupo['flashcards'])) {
            continue;
        }

        foreach ($grupo['flashcards'] as $flashcard) {

            $key = md5(
                ($grupo['categoria'] ?? '') .
                ($flashcard['question'] ?? '') .
                ($flashcard['summary'] ?? '')
            );

            if (isset($seen[$key])) continue;

            $seen[$key] = true;

            $data[] = [
                'categoryId' => $categorias[$grupo['categoria']] ?? null,
                "question"   => $flashcard['question'] ?? "",
                "type"       => $grupo["tipo"] ?? "",
                "content"    => $flashcard['summary'] ?? "",
                "options"    => $flashcard['multiple_choice'] ?? [],
            ];
        }
    }

    // ✅ AGORA SIM retorna
    return response()->json($data, 200);
}


}