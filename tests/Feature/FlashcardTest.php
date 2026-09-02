<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\FlashcardItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FlashcardTest extends TestCase
{
    use RefreshDatabase;

    private function clientUser(): User
    {
        return User::factory()->create(['role' => 'client']);
    }

    private function categoriaDe(User $user, string $nome = 'Matemática'): Categoria
    {
        return Categoria::create([
            'nome_categoria' => $nome, 'icon' => '🧮', 'color' => 'x', 'user_id' => $user->id,
        ]);
    }

    public function test_criacao_de_flashcard_persiste_localmente_e_envia_ao_python(): void
    {
        Http::fake(['*/submit_flash' => Http::response(['status' => 'ok'], 200)]);

        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);

        $response = $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id,
            'type' => 'summary',
            'question' => 'Capital do Brasil',
            'content' => 'Brasília',
        ]);

        $response->assertStatus(201)->assertJsonStructure(['id', 'categoryId', 'type', 'question']);

        $this->assertDatabaseHas('flashcard_items', [
            'user_id' => $user->id,
            'categoria_id' => $categoria->id,
            'type' => 'summary',
        ]);

        Http::assertSent(function ($request) use ($response, $user) {
            return $request->url() === 'http://127.0.0.1:5000/submit_flash'
                && $request['flashcard_id'] === $response->json('id')
                && $request['usuario'] === $user->id;
        });
    }

    public function test_criacao_com_categoria_de_outro_usuario_e_rejeitada(): void
    {
        $dono = $this->clientUser();
        $atacante = $this->clientUser();
        $categoriaDoDono = $this->categoriaDe($dono);

        $response = $this->actingAs($atacante)->postJson('/api/flashcard', [
            'categoryId' => $categoriaDoDono->id,
            'type' => 'summary',
            'question' => 'Teste',
            'content' => 'Teste',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, FlashcardItem::count());
    }

    public function test_falha_do_python_nao_deixa_registro_orfao_no_mysql(): void
    {
        Http::fake(['*/submit_flash' => Http::response(['error' => 'boom'], 500)]);

        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);

        $response = $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id,
            'type' => 'summary',
            'question' => 'Capital do Brasil',
            'content' => 'Brasília',
        ]);

        $response->assertStatus(502);
        $this->assertSame(0, FlashcardItem::count(), 'A linha local deve ser desfeita (rollback) quando o Python falha.');
    }

    public function test_timeout_do_python_retorna_502_sem_vazar_detalhes_internos(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);

        $response = $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id,
            'type' => 'summary',
            'question' => 'Capital do Brasil',
            'content' => 'Brasília',
        ]);

        $response->assertStatus(502);
        $response->assertJsonMissingPath('exception');
        $this->assertSame(0, FlashcardItem::count());
    }

    public function test_falha_transitoria_do_python_e_recuperada_por_retry(): void
    {
        // Primeira tentativa falha (500 transitório), segunda tem sucesso.
        // O retry deve engolir a falha transitória e concluir com 201,
        // sem deixar MySQL e Neo4j inconsistentes.
        Http::fake([
            '*/submit_flash' => Http::sequence()
                ->push(['error' => 'boom'], 500)
                ->push(['status' => 'ok'], 200),
        ]);

        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);

        $response = $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id,
            'type' => 'summary',
            'question' => 'Capital do Brasil',
            'content' => 'Brasília',
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, FlashcardItem::count(), 'O retry deve recuperar a falha transitória e persistir normalmente.');

        Http::assertSentCount(2);
    }

    public function test_falha_definitiva_4xx_do_python_nao_e_reenviada(): void
    {
        // 4xx é erro definitivo (ex.: payload rejeitado) - não deve
        // consumir tentativas de retry, só as transitórias (timeout/5xx).
        Http::fake(['*/submit_flash' => Http::response(['error' => 'invalid'], 422)]);

        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);

        $response = $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id,
            'type' => 'summary',
            'question' => 'Capital do Brasil',
            'content' => 'Brasília',
        ]);

        $response->assertStatus(502);
        $this->assertSame(0, FlashcardItem::count());

        Http::assertSentCount(1);
    }

    public function test_tipo_audio_envia_translation_e_audio_url_ao_python(): void
    {
        Http::fake(['*/submit_flash' => Http::response(['status' => 'ok'], 200)]);

        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);

        $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id,
            'type' => 'audio',
            'question' => 'Pronuncie: Hello',
            'translation' => 'Olá',
            'audioUrl' => 'data:audio/mp3;base64,AAA',
        ])->assertStatus(201);

        Http::assertSent(function ($request) {
            return $request['flashcard']['translation'] === 'Olá'
                && $request['flashcard']['audioUrl'] === 'data:audio/mp3;base64,AAA';
        });
    }

    public function test_index_so_retorna_flashcards_do_usuario_mesmo_que_python_vaze_outros(): void
    {
        $userA = $this->clientUser();
        $userB = $this->clientUser();
        $categoriaA = $this->categoriaDe($userA, 'Categoria A');

        FlashcardItem::create(['user_id' => $userA->id, 'categoria_id' => $categoriaA->id, 'type' => 'summary']);

        // Resposta do Python "vazando" (propositalmente, para o teste)
        // dados de outro usuário junto - o Laravel precisa filtrar de novo.
        Http::fake(['*/flashcard/index*' => Http::response([
            [
                'usuario' => $userA->id,
                'categoria' => 'Categoria A',
                'tipo' => 'summary',
                'flashcards' => [
                    ['question' => 'Capital do Brasil', 'summary' => 'Brasília'],
                ],
            ],
            [
                'usuario' => $userB->id,
                'categoria' => 'Categoria de B',
                'tipo' => 'summary',
                'flashcards' => [
                    ['question' => 'Não deveria aparecer', 'summary' => 'x'],
                ],
            ],
        ], 200)]);

        $response = $this->actingAs($userA)->getJson('/api/flashcard/index');

        $response->assertStatus(200)->assertJsonCount(1);
        $this->assertSame('Capital do Brasil', $response->json('0.question'));
    }

    /**
     * Regressão do bug corrigido: update parcial (sem "type" no payload)
     * fazia buildContentPayload recalcular o tipo a partir de $data
     * isoladamente, resultando em tipo="" e no campo "summary" nunca
     * sendo enviado ao Python - o conteúdo era efetivamente apagado no
     * Python mesmo o Laravel respondendo 200.
     */
    public function test_update_parcial_sem_type_reenvia_o_tipo_e_conteudo_corretos_ao_python(): void
    {
        Http::fake(['*/flashcard/*' => Http::response(['status' => 'ok'], 200)]);

        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);
        $item = FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);

        $this->actingAs($user)
            ->putJson("/api/flashcard/{$item->id}", ['question' => 'Atualizada', 'content' => 'Novo conteúdo'])
            ->assertStatus(200);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && $request['tipo'] === 'summary'
                && $request['flashcard']['summary'] === 'Novo conteúdo';
        });
    }

    public function test_dono_atualiza_e_exclui_o_proprio_flashcard(): void
    {
        Http::fake([
            '*/flashcard/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);
        $item = FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);

        $this->actingAs($user)
            ->putJson("/api/flashcard/{$item->id}", ['question' => 'Atualizada', 'content' => 'Novo conteúdo'])
            ->assertStatus(200)
            ->assertJson(['id' => $item->id, 'question' => 'Atualizada']);

        $this->actingAs($user)
            ->deleteJson("/api/flashcard/{$item->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('flashcard_items', ['id' => $item->id]);

        Http::assertSent(function ($request) use ($item, $user) {
            return $request->method() === 'DELETE'
                && $request->url() === "http://127.0.0.1:5000/flashcard/{$item->id}"
                && $request['usuario'] === $user->id;
        });
    }

    public function test_falha_do_python_no_update_desfaz_alteracoes_no_mysql(): void
    {
        Http::fake(['*/flashcard/*' => Http::response(['error' => 'boom'], 500)]);

        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);
        $item = FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);

        $response = $this->actingAs($user)
            ->putJson("/api/flashcard/{$item->id}", ['question' => 'Atualizada', 'content' => 'Novo conteúdo']);

        $response->assertStatus(502);
        $this->assertDatabaseHas('flashcard_items', ['id' => $item->id, 'type' => 'summary']);
    }

    public function test_falha_do_python_no_delete_mantem_o_registro_no_mysql(): void
    {
        Http::fake(['*/flashcard/*' => Http::response(['error' => 'boom'], 500)]);

        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);
        $item = FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);

        $response = $this->actingAs($user)->deleteJson("/api/flashcard/{$item->id}");

        $response->assertStatus(502);
        $this->assertDatabaseHas('flashcard_items', ['id' => $item->id]);
    }

    public function test_falha_transitoria_no_delete_e_recuperada_por_retry_mesmo_com_python_ja_tendo_removido(): void
    {
        // Cenário do gap de idempotência: a 1ª tentativa perde a resposta
        // (timeout) depois do Python já ter removido o node no Neo4j; a 2ª
        // tentativa (retry) encontra o node ausente e, por ser idempotente,
        // responde 204 em vez de 404 - então o retry conclui com sucesso e
        // o MySQL não sofre rollback por um 404 espúrio.
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('Connection timed out');
            }

            return Http::response('', 204);
        });

        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);
        $item = FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);

        $response = $this->actingAs($user)->deleteJson("/api/flashcard/{$item->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('flashcard_items', ['id' => $item->id]);
        // Http::fake só registra respostas em Http::recorded(), não tentativas
        // que lançam exceção - por isso o contador de tentativas é local.
        $this->assertSame(2, $attempts, 'A 1ª tentativa deve falhar por timeout e a 2ª (retry) deve suceder.');
    }

    public function test_usuario_nao_atualiza_nem_exclui_flashcard_de_outro(): void
    {
        $dono = $this->clientUser();
        $atacante = $this->clientUser();
        $categoria = $this->categoriaDe($dono);
        $item = FlashcardItem::create(['user_id' => $dono->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);

        $this->actingAs($atacante)
            ->putJson("/api/flashcard/{$item->id}", ['question' => 'Hackeado'])
            ->assertStatus(403);

        $this->actingAs($atacante)
            ->deleteJson("/api/flashcard/{$item->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('flashcard_items', ['id' => $item->id]);
        Http::assertNothingSent();
    }
}
