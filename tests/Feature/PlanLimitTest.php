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
 * PlanLimitService: os números oficiais (Gratuito/Premium/Institucional)
 * já existem via seed_planos_oficiais - mas a maioria destes testes usa
 * um plano "Teste" criado no próprio teste (ativarPlano) para exercitar o
 * MECANISMO de bloqueio isoladamente, sem acoplar às constantes de
 * produto (que podem mudar). Testes que dependem especificamente do
 * fallback Gratuito usam o plano seedado de propósito.
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

    public function test_usuario_sem_plano_selecionado_cai_no_fallback_gratuito(): void
    {
        // resolveActivePlano cai para o Gratuito seedado quando não há
        // plano_selecionado ativo (fail-closed) - texto passa (dentro do
        // limite gratuito), mas áudio é bloqueado pela mesma regra do
        // Gratuito, provando que o fallback realmente aplica as regras
        // do plano e não "sem limite".
        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);

        Http::fake(['*/submit_flash' => Http::response(['status' => 'ok'], 200)]);

        $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id, 'type' => 'summary', 'question' => 'P1', 'content' => 'C1',
        ])->assertStatus(201);

        $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id, 'type' => 'audio', 'question' => 'P2', 'translation' => 'x', 'audioUrl' => 'x',
        ])->assertStatus(422);

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

    public function test_flashcard_fora_da_janela_de_30_dias_nao_conta_para_o_limite(): void
    {
        // Decisão de produto: janela rolante de 30 dias corridos a partir
        // do cadastro (user.created_at), não mês calendário nem contagem
        // total histórica - um flashcard criado no período anterior não
        // deve contar para o limite do período atual.
        $user = $this->clientUser();
        $user->forceFill(['created_at' => now()->subDays(40)])->save();
        $categoria = $this->categoriaDe($user);
        $this->ativarPlano($user, 1);

        // created_at não está em $fillable (por bom motivo - não deve ser
        // setável via mass assignment em produção); forceFill só neste
        // teste para simular um item de um período anterior.
        FlashcardItem::create(['user_id' => $user->id, 'categoria_id' => $categoria->id, 'type' => 'summary'])
            ->forceFill(['created_at' => now()->subDays(35)])
            ->save();

        Http::fake(['*/submit_flash' => Http::response(['status' => 'ok'], 200)]);

        $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id, 'type' => 'summary', 'question' => 'P', 'content' => 'C',
        ])->assertStatus(201);

        $this->assertSame(2, FlashcardItem::where('user_id', $user->id)->count());
    }

    public function test_limite_de_categorias_bloqueia_ao_atingir_o_teto(): void
    {
        $user = $this->clientUser();
        $plano = Plano::create([
            'name_plano' => 'TesteCategoria', 'Descricao' => 'x', 'valor' => 0, 'desconto' => 0,
            'limite_categorias' => 1,
        ]);
        PlanoSelecionado::create(['id_usuario' => $user->id, 'id_plano' => $plano->id, 'status' => 1]);
        $this->categoriaDe($user, 'Primeira');

        $response = $this->actingAs($user)->postJson('/api/categoria', [
            'nome_categoria' => 'Segunda', 'icon' => '📚', 'color' => 'x',
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, Categoria::where('user_id', $user->id)->count());
    }

    public function test_plano_sem_permitir_audio_bloqueia_criacao_de_flashcard_audio(): void
    {
        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);
        $plano = Plano::create([
            'name_plano' => 'TesteSemAudio', 'Descricao' => 'x', 'valor' => 0, 'desconto' => 0,
            'permite_audio' => false, 'permite_multipla_escolha' => false,
        ]);
        PlanoSelecionado::create(['id_usuario' => $user->id, 'id_plano' => $plano->id, 'status' => 1]);

        Http::fake(['*/submit_flash' => Http::response(['status' => 'ok'], 200)]);

        $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id, 'type' => 'audio', 'question' => 'P', 'translation' => 'x', 'audioUrl' => 'x',
        ])->assertStatus(422);

        $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id, 'type' => 'multiple-choice', 'question' => 'P',
            'options' => [['text' => 'a', 'isCorrect' => true], ['text' => 'b', 'isCorrect' => false]],
        ])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_plano_permitindo_audio_e_multipla_escolha_libera_criacao(): void
    {
        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);
        $plano = Plano::create([
            'name_plano' => 'TesteComAudio', 'Descricao' => 'x', 'valor' => 0, 'desconto' => 0,
            'permite_audio' => true, 'permite_multipla_escolha' => true,
        ]);
        PlanoSelecionado::create(['id_usuario' => $user->id, 'id_plano' => $plano->id, 'status' => 1]);

        Http::fake(['*/submit_flash' => Http::response(['status' => 'ok'], 200)]);

        $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id, 'type' => 'audio', 'question' => 'P', 'translation' => 'x', 'audioUrl' => 'x',
        ])->assertStatus(201);
    }

    public function test_update_nao_pode_trocar_tipo_para_um_nao_permitido_no_plano(): void
    {
        $user = $this->clientUser();
        $categoria = $this->categoriaDe($user);
        Http::fake(['*/*' => Http::response(['status' => 'ok'], 200)]);

        $item = $this->actingAs($user)->postJson('/api/flashcard', [
            'categoryId' => $categoria->id, 'type' => 'summary', 'question' => 'P', 'content' => 'C',
        ])->json();

        // Sem plano ativo -> fallback Gratuito, que não permite áudio.
        $this->actingAs($user)->putJson("/api/flashcard/{$item['id']}", [
            'type' => 'audio', 'question' => 'P', 'translation' => 'x', 'audioUrl' => 'x',
        ])->assertStatus(422);

        $this->assertSame('summary', FlashcardItem::find($item['id'])->type);
    }
}
