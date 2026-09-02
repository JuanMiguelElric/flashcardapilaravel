<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\FlashcardItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CategoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_autenticado_cria_categoria(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/categoria', [
            'nome_categoria' => 'Matemática',
            'icon' => '🧮',
            'color' => '221 83% 53%',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'nome_categoria', 'icon', 'color', 'user_id'])
            ->assertJson([
                'nome_categoria' => 'Matemática',
                'user_id' => $user->id,
            ]);

        $this->assertDatabaseHas('categorias', [
            'nome_categoria' => 'Matemática',
            'user_id' => $user->id,
        ]);
    }

    public function test_criar_categoria_sem_autenticacao_retorna_401(): void
    {
        $this->postJson('/api/categoria', [
            'nome_categoria' => 'Matemática',
            'icon' => '🧮',
            'color' => '221 83% 53%',
        ])->assertStatus(401);
    }

    public function test_criar_categoria_com_dados_invalidos_retorna_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/categoria', [
            'nome_categoria' => '',
        ])->assertStatus(422);
    }

    /**
     * Regressão do bug corrigido: /categoria/index vazava categorias de
     * todos os usuários. Cada usuário só pode ver as suas.
     */
    public function test_index_retorna_apenas_categorias_do_usuario_autenticado(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Categoria::create(['nome_categoria' => 'Da A', 'icon' => '📚', 'color' => 'x', 'user_id' => $userA->id]);
        Categoria::create(['nome_categoria' => 'Da B', 'icon' => '📚', 'color' => 'x', 'user_id' => $userB->id]);

        $response = $this->actingAs($userA)->getJson('/api/categoria/index');

        $response->assertStatus(200)->assertJsonCount(1);
        $this->assertSame('Da A', $response->json('0.nome_categoria'));
    }

    public function test_usuario_nao_acessa_categoria_de_outro_usuario(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $categoriaDeB = Categoria::create([
            'nome_categoria' => 'Da B', 'icon' => '📚', 'color' => 'x', 'user_id' => $userB->id,
        ]);

        $this->actingAs($userA)->getJson("/api/categoria/{$categoriaDeB->id}")->assertStatus(403);
        $this->actingAs($userA)->putJson("/api/categoria/{$categoriaDeB->id}", ['nome_categoria' => 'Hackeado'])->assertStatus(403);
        $this->actingAs($userA)->deleteJson("/api/categoria/{$categoriaDeB->id}")->assertStatus(403);

        $this->assertDatabaseHas('categorias', ['id' => $categoriaDeB->id, 'nome_categoria' => 'Da B']);
    }

    public function test_dono_atualiza_e_exclui_a_propria_categoria(): void
    {
        $user = User::factory()->create();
        $categoria = Categoria::create([
            'nome_categoria' => 'Original', 'icon' => '📚', 'color' => 'x', 'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->putJson("/api/categoria/{$categoria->id}", ['nome_categoria' => 'Atualizada'])
            ->assertStatus(200)
            ->assertJson(['nome_categoria' => 'Atualizada']);

        $this->actingAs($user)
            ->deleteJson("/api/categoria/{$categoria->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
    }

    /**
     * Regressão: flashcard_items.categoria_id tem cascadeOnDelete() no
     * MySQL. Sem a correção, excluir uma categoria com flashcards apagava
     * os flashcard_items em cascata sem nunca avisar o Python, deixando
     * nodes :flashcard órfãos no Neo4j.
     */
    public function test_categoria_sem_flashcards_e_excluida_sem_chamar_python(): void
    {
        Http::fake(); // qualquer chamada HTTP falha o teste (nenhuma é esperada)

        $user = User::factory()->create(['role' => 'client']);
        $categoria = Categoria::create([
            'nome_categoria' => 'Vazia', 'icon' => '📚', 'color' => 'x', 'user_id' => $user->id,
        ]);

        $this->actingAs($user)->deleteJson("/api/categoria/{$categoria->id}")->assertStatus(204);

        $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
        Http::assertNothingSent();
    }

    public function test_categoria_com_flashcards_remove_conteudo_no_python_antes_de_excluir_no_mysql(): void
    {
        Http::fake(['*/flashcard/*' => Http::response(['status' => 'ok'], 200)]);

        $user = User::factory()->create(['role' => 'client']);
        $categoria = Categoria::create([
            'nome_categoria' => 'Matemática', 'icon' => '🧮', 'color' => 'x', 'user_id' => $user->id,
        ]);
        $item1 = FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);
        $item2 = FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);

        $this->actingAs($user)->deleteJson("/api/categoria/{$categoria->id}")->assertStatus(204);

        $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
        $this->assertDatabaseMissing('flashcard_items', ['id' => $item1->id]);
        $this->assertDatabaseMissing('flashcard_items', ['id' => $item2->id]);

        Http::assertSent(fn ($request) => $request->url() === "http://127.0.0.1:5000/flashcard/{$item1->id}"
            && $request->method() === 'DELETE'
            && $request['usuario'] === $user->id);
        Http::assertSent(fn ($request) => $request->url() === "http://127.0.0.1:5000/flashcard/{$item2->id}"
            && $request->method() === 'DELETE'
            && $request['usuario'] === $user->id);
        Http::assertSentCount(2);
    }

    public function test_falha_do_python_ao_excluir_categoria_preserva_categoria_e_flashcards_no_mysql(): void
    {
        Http::fake(['*/flashcard/*' => Http::response(['error' => 'boom'], 500)]);

        $user = User::factory()->create(['role' => 'client']);
        $categoria = Categoria::create([
            'nome_categoria' => 'Matemática', 'icon' => '🧮', 'color' => 'x', 'user_id' => $user->id,
        ]);
        $item = FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);

        $response = $this->actingAs($user)->deleteJson("/api/categoria/{$categoria->id}");

        $response->assertStatus(502);
        $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
        $this->assertDatabaseHas('flashcard_items', ['id' => $item->id]);
    }

    public function test_usuario_nao_exclui_categoria_de_outro_usuario_mesmo_com_flashcards(): void
    {
        Http::fake(); // nenhuma chamada deveria acontecer - 403 antes de tocar em Python

        $dono = User::factory()->create(['role' => 'client']);
        $atacante = User::factory()->create(['role' => 'client']);
        $categoriaDoDono = Categoria::create([
            'nome_categoria' => 'Do Dono', 'icon' => '📚', 'color' => 'x', 'user_id' => $dono->id,
        ]);
        $item = FlashcardItem::create(['user_id' => $dono->id, 'categoria_id' => $categoriaDoDono->id, 'type' => 'summary']);

        $this->actingAs($atacante)->deleteJson("/api/categoria/{$categoriaDoDono->id}")->assertStatus(403);

        $this->assertDatabaseHas('categorias', ['id' => $categoriaDoDono->id]);
        $this->assertDatabaseHas('flashcard_items', ['id' => $item->id]);
        Http::assertNothingSent();
    }
}
