<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\FlashcardItem;
use App\Models\Plano;
use App\Models\PlanoSelecionado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PlanLimitService: só existe mecanismo, não números reais - ver
 * PlanLimitService e a migration de planos.limite_flashcards. Estes testes
 * provam o MECANISMO (respeita/bloqueia quando um limite está configurado),
 * usando planos criados no próprio teste - não valores de produção, que
 * não existem em lugar nenhum ainda.
 */
class PlanLimitTest extends TestCase
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

    private function ativarPlano(User $user, ?int $limiteFlashcards): Plano
    {
        $plano = Plano::create([
            'name_plano' => 'Teste', 'Descricao' => 'Plano de teste', 'valor' => 0, 'desconto' => 0,
            'limite_flashcards' => $limiteFlashcards,
        ]);

        PlanoSelecionado::create(['id_usuario' => $user->id, 'id_plano' => $plano->id, 'status' => 1]);

        return $plano;
    }

    public function test_usuario_sem_plano_selecionado_cria_flashcards_livremente(): void
    {
        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);

        Http::fake(['*/submit_flash' => Http::response(['status' => 'ok'], 200)]);

        $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id, 'type' => 'summary', 'question' => 'P1', 'content' => 'C1',
        ])->assertStatus(201);

        $this->assertSame(1, FlashcardItem::where('user_id', $user->id)->count());
    }

    public function test_plano_com_limite_nulo_nao_bloqueia(): void
    {
        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);
        $this->ativarPlano($user, null);

        Http::fake(['*/submit_flash' => Http::response(['status' => 'ok'], 200)]);

        $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id, 'type' => 'summary', 'question' => 'P1', 'content' => 'C1',
        ])->assertStatus(201);

        $this->assertSame(1, FlashcardItem::where('user_id', $user->id)->count());
    }

    public function test_usuario_dentro_do_limite_pode_criar(): void
    {
        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);
        $this->ativarPlano($user, 2);

        // 1 existente, limite 2 -> ainda pode criar mais 1 (ficará em 2, no limite).
        FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);

        Http::fake(['*/submit_flash' => Http::response(['status' => 'ok'], 200)]);

        $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id, 'type' => 'summary', 'question' => 'P2', 'content' => 'C2',
        ])->assertStatus(201);

        $this->assertSame(2, FlashcardItem::where('user_id', $user->id)->count());
    }

    public function test_usuario_no_limite_e_bloqueado_ao_tentar_criar_mais_um(): void
    {
        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);
        $this->ativarPlano($user, 2);

        FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);
        FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);

        Http::fake(['*/submit_flash' => Http::response(['status' => 'ok'], 200)]);

        $response = $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id, 'type' => 'summary', 'question' => 'P3', 'content' => 'C3',
        ]);

        $response->assertStatus(422);
        $this->assertSame(2, FlashcardItem::where('user_id', $user->id)->count());
        Http::assertNothingSent();
    }

    public function test_usuario_acima_do_limite_continua_bloqueado(): void
    {
        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);
        $this->ativarPlano($user, 1);

        FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);
        FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary']);

        Http::fake(['*/submit_flash' => Http::response(['status' => 'ok'], 200)]);

        $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id, 'type' => 'summary', 'question' => 'P3', 'content' => 'C3',
        ])->assertStatus(422);

        $this->assertSame(2, FlashcardItem::where('user_id', $user->id)->count());
    }

    public function test_limite_e_por_usuario_nao_afeta_outros(): void
    {
        $userA = $this->clientUser();
        $userB = $this->clientUser();
        $categoriaA = $this->categoriaDe($userA);
        $categoriaB = $this->categoriaDe($userB);
        $this->ativarPlano($userA, 1);

        FlashcardItem::create(['user_id' => $userA->id, 'categoria_id' => $categoriaA->id, 'type' => 'summary']);

        Http::fake(['*/submit_flash' => Http::response(['status' => 'ok'], 200)]);

        // userA já está no limite (1) - bloqueado.
        $this->actingAs($userA)->postJson('/api/flashcard', [
            'categoryId' => $categoriaA->id, 'type' => 'summary', 'question' => 'P', 'content' => 'C',
        ])->assertStatus(422);

        // userB não tem plano selecionado - livre.
        $this->actingAs($userB)->postJson('/api/flashcard', [
            'categoryId' => $categoriaB->id, 'type' => 'summary', 'question' => 'P', 'content' => 'C',
        ])->assertStatus(201);
    }
}
