<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\FlashcardItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_requisicao_sem_autenticacao_nao_acessa_dashboard(): void
    {
        $this->getJson('/api/dashboard')->assertStatus(401);
    }

    public function test_dashboard_vazio_para_usuario_sem_flashcards_ou_categorias(): void
    {
        $user = $this->clientUser();

        $response = $this->actingAs($user)->getJson('/api/dashboard');

        $response->assertStatus(200)->assertJson([
            'total_flashcards' => 0,
            'total_categorias' => 0,
            'flashcards_by_categoria' => [],
            'flashcards_ultimos_7_dias' => 0,
        ]);
    }

    public function test_dashboard_conta_flashcards_e_categorias_do_usuario_autenticado(): void
    {
        $user = $this->clientUser();
        $matematica = $this->categoriaDe($user, 'Matemática');
        $historia = $this->categoriaDe($user, 'História');

        FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $matematica->id, 'type' => 'summary']);
        FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $matematica->id, 'type' => 'summary']);
        FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $historia->id, 'type' => 'summary']);

        $response = $this->actingAs($user)->getJson('/api/dashboard');

        $response->assertStatus(200);
        $response->assertJson([
            'total_flashcards' => 3,
            'total_categorias' => 2,
            'flashcards_ultimos_7_dias' => 3,
        ]);

        $porCategoria = collect($response->json('flashcards_by_categoria'))->keyBy('nome_categoria');
        $this->assertSame(2, $porCategoria['Matemática']['total']);
        $this->assertSame(1, $porCategoria['História']['total']);
    }

    public function test_dashboard_inclui_categoria_sem_flashcards_com_total_zero(): void
    {
        $user = $this->clientUser();
        $this->categoriaDe($user, 'Vazia');

        $response = $this->actingAs($user)->getJson('/api/dashboard');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('flashcards_by_categoria.0.total'));
    }

    public function test_dashboard_nao_mistura_dados_de_outro_usuario(): void
    {
        $userA = $this->clientUser();
        $userB = $this->clientUser();
        $categoriaA = $this->categoriaDe($userA, 'De A');
        $categoriaB = $this->categoriaDe($userB, 'De B');

        FlashcardItem::create(['user_id' => $userA->id, 'categoria_id' => $categoriaA->id, 'type' => 'summary']);
        FlashcardItem::create(['user_id' => $userB->id, 'categoria_id' => $categoriaB->id, 'type' => 'summary']);
        FlashcardItem::create(['user_id' => $userB->id, 'categoria_id' => $categoriaB->id, 'type' => 'summary']);

        $response = $this->actingAs($userA)->getJson('/api/dashboard');

        $response->assertStatus(200)->assertJson([
            'total_flashcards' => 1,
            'total_categorias' => 1,
        ]);
    }
}
