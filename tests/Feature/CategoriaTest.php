<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
