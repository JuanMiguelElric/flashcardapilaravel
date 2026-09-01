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
        foreach (['client', 'premium'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->postJson('/api/plano', [
                'name_plano' => 'Premium',
                'Descricao' => 'Acesso completo',
                'valor' => 29.9,
                'desconto' => 0,
            ])->assertStatus(403);
        }

        $this->assertDatabaseMissing('planos', ['name_plano' => 'Premium']);
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
}
