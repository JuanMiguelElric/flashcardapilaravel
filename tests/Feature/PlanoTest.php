<?php

namespace Tests\Feature;

use App\Models\Plano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cria_plano(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/api/plano', [
            'name_plano' => 'Premium',
            'Descricao' => 'Acesso completo',
            'valor' => 29.9,
            'desconto' => 0,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('planos', ['name_plano' => 'Premium']);
    }

    public function test_usuario_nao_admin_nao_cria_plano(): void
    {
        // 'premium' deixou de ser um role valido (migrate_role_premium_
        // to_plano_and_drop_enum_value) - so 'client' resta para testar
        // aqui.
        $user = User::factory()->create(['role' => 'client']);

        $this->actingAs($user)->postJson('/api/plano', [
            'name_plano' => 'Turbo',
            'Descricao' => 'Acesso completo',
            'valor' => 29.9,
            'desconto' => 0,
        ])->assertStatus(403);

        $this->assertDatabaseMissing('planos', ['name_plano' => 'Turbo']);
    }

    public function test_requisicao_sem_autenticacao_nao_acessa_rotas_de_plano(): void
    {
        $this->postJson('/api/plano', [
            'name_plano' => 'Premium',
            'Descricao' => 'Acesso completo',
            'valor' => 29.9,
            'desconto' => 0,
        ])->assertStatus(401);
    }

    public function test_admin_atualiza_plano_existente(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plano = Plano::create([
            'name_plano' => 'Gratuito', 'Descricao' => 'Básico', 'valor' => 0, 'desconto' => 0,
        ]);

        $this->actingAs($admin)->putJson("/api/plano/{$plano->id}", [
            'name_plano' => 'Gratuito Plus',
            'Descricao' => 'Básico +',
            'valor' => 0,
            'desconto' => 0,
        ])->assertStatus(201);

        $this->assertDatabaseHas('planos', ['id' => $plano->id, 'name_plano' => 'Gratuito Plus']);
    }

    public function test_admin_define_limite_de_flashcards_do_plano(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/api/plano', [
            'name_plano' => 'Premium',
            'Descricao' => 'Acesso completo',
            'valor' => 29.9,
            'desconto' => 0,
            'limite_flashcards' => 500,
        ])->assertStatus(201);

        $this->assertDatabaseHas('planos', ['name_plano' => 'Premium', 'limite_flashcards' => 500]);
    }

    public function test_admin_lista_planos(): void
    {
        // seed_planos_oficiais ja garante Gratuito/Premium/Institucional -
        // a asserção conta a partir dessa base em vez de assumir tabela
        // vazia.
        $admin = User::factory()->create(['role' => 'admin']);
        $totalAntes = Plano::count();
        Plano::create(['name_plano' => 'Turbo', 'Descricao' => 'Completo', 'valor' => 39.9, 'desconto' => 0]);

        $response = $this->actingAs($admin)->getJson('/api/plano');

        $response->assertStatus(200)->assertJsonCount($totalAntes + 1);
    }

    public function test_usuario_nao_admin_nao_lista_nem_ve_nem_exclui_planos(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $plano = Plano::create(['name_plano' => 'Gratuito', 'Descricao' => 'Básico', 'valor' => 0, 'desconto' => 0]);

        $this->actingAs($client)->getJson('/api/plano')->assertStatus(403);
        $this->actingAs($client)->getJson("/api/plano/{$plano->id}")->assertStatus(403);
        $this->actingAs($client)->deleteJson("/api/plano/{$plano->id}")->assertStatus(403);
    }

    public function test_admin_ve_e_exclui_plano_especifico(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plano = Plano::create(['name_plano' => 'Gratuito', 'Descricao' => 'Básico', 'valor' => 0, 'desconto' => 0]);

        $this->actingAs($admin)->getJson("/api/plano/{$plano->id}")
            ->assertStatus(200)
            ->assertJson(['id' => $plano->id, 'name_plano' => 'Gratuito']);

        $this->actingAs($admin)->deleteJson("/api/plano/{$plano->id}")->assertStatus(204);
        $this->assertDatabaseMissing('planos', ['id' => $plano->id]);
    }

    public function test_usuario_seleciona_o_proprio_plano(): void
    {
        // Premium ja existe via seed_planos_oficiais - reutiliza em vez de
        // criar um duplicado (gravarPlano busca por name_plano e pegaria
        // o primeiro registro, não necessariamente o criado aqui).
        $user = User::factory()->create(['role' => 'client']);
        $plano = Plano::where('name_plano', 'Premium')->firstOrFail();

        $this->actingAs($user)->postJson('/api/plano/selecionar', ['name_plano' => 'Premium'])
            ->assertStatus(201);

        $this->assertDatabaseHas('plano_selecionado', [
            'id_usuario' => $user->id, 'id_plano' => $plano->id, 'status' => 1,
        ]);
        $this->assertSame($plano->id, $user->fresh()->planoAtivo()->id);
    }

    public function test_selecionar_plano_inexistente_retorna_404(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $this->actingAs($user)->postJson('/api/plano/selecionar', ['name_plano' => 'Não Existe'])
            ->assertStatus(404);
    }

    public function test_trocar_de_plano_desativa_a_selecao_anterior(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $gratuito = Plano::where('name_plano', 'Gratuito')->firstOrFail();
        $premium = Plano::where('name_plano', 'Premium')->firstOrFail();

        $this->actingAs($user)->postJson('/api/plano/selecionar', ['name_plano' => 'Gratuito'])->assertStatus(201);
        $this->actingAs($user)->postJson('/api/plano/selecionar', ['name_plano' => 'Premium'])->assertStatus(201);

        $this->assertDatabaseHas('plano_selecionado', [
            'id_usuario' => $user->id, 'id_plano' => $gratuito->id, 'status' => 0,
        ]);
        $this->assertDatabaseHas('plano_selecionado', [
            'id_usuario' => $user->id, 'id_plano' => $premium->id, 'status' => 1,
        ]);
        $this->assertSame($premium->id, $user->fresh()->planoAtivo()->id);
    }
}
