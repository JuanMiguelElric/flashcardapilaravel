<?php

namespace Tests\Feature;

use App\Models\Plano;
use App\Models\PlanoSelecionado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssinaturaTest extends TestCase
{
    use RefreshDatabase;

    private function clientUser(): User
    {
        return User::factory()->create(['role' => 'client']);
    }

    public function test_checkout_cria_assinatura_pendente_e_devolve_url_de_checkout(): void
    {
        $user = $this->clientUser();
        $premium = Plano::where('name_plano', 'Premium')->firstOrFail();

        Http::fake(['*/preapproval' => Http::response([
            'id' => 'mp-sub-123',
            'init_point' => 'https://mercadopago.com/checkout/mp-sub-123',
        ], 201)]);

        $response = $this->actingAs($user)->postJson('/api/assinatura/checkout', [
            'name_plano' => 'Premium',
        ]);

        $response->assertStatus(201)->assertJson([
            'checkout_url' => 'https://mercadopago.com/checkout/mp-sub-123',
        ]);

        $this->assertDatabaseHas('plano_selecionado', [
            'id_usuario' => $user->id, 'id_plano' => $premium->id,
            'status' => 0, 'mp_subscription_id' => 'mp-sub-123',
        ]);
        // Ainda não está ativo - só o webhook confirma.
        $this->assertNotSame($premium->id, $user->fresh()->planoAtivo()?->id);
    }

    public function test_checkout_de_plano_gratuito_e_rejeitado(): void
    {
        $user = $this->clientUser();

        $response = $this->actingAs($user)->postJson('/api/assinatura/checkout', [
            'name_plano' => 'Gratuito',
        ]);

        $response->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_checkout_de_plano_inexistente_retorna_404(): void
    {
        $user = $this->clientUser();

        $this->actingAs($user)->postJson('/api/assinatura/checkout', [
            'name_plano' => 'NaoExiste',
        ])->assertStatus(404);
    }

    public function test_webhook_sem_secret_configurado_ativa_assinatura_autorizada(): void
    {
        // MERCADOPAGO_WEBHOOK_SECRET vazio no .env de teste - validação de
        // assinatura é pulada (ver AssinaturaController::assertAssinaturaValida).
        $user = $this->clientUser();
        $premium = Plano::where('name_plano', 'Premium')->firstOrFail();
        $selecao = PlanoSelecionado::create([
            'id_usuario' => $user->id, 'id_plano' => $premium->id,
            'status' => 0, 'mp_subscription_id' => 'mp-sub-123',
        ]);

        Http::fake(['*/preapproval/mp-sub-123' => Http::response([
            'id' => 'mp-sub-123', 'status' => 'authorized',
        ], 200)]);

        $response = $this->postJson('/api/webhooks/mercadopago', [
            'data' => ['id' => 'mp-sub-123'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('plano_selecionado', ['id' => $selecao->id, 'status' => 1]);
        $this->assertSame($premium->id, $user->fresh()->planoAtivo()->id);
    }

    public function test_webhook_desativa_selecao_anterior_ao_ativar_a_nova(): void
    {
        $user = $this->clientUser();
        $gratuito = Plano::where('name_plano', 'Gratuito')->firstOrFail();
        $premium = Plano::where('name_plano', 'Premium')->firstOrFail();
        PlanoSelecionado::create(['id_usuario' => $user->id, 'id_plano' => $gratuito->id, 'status' => 1]);
        $selecao = PlanoSelecionado::create([
            'id_usuario' => $user->id, 'id_plano' => $premium->id,
            'status' => 0, 'mp_subscription_id' => 'mp-sub-456',
        ]);

        Http::fake(['*/preapproval/mp-sub-456' => Http::response([
            'id' => 'mp-sub-456', 'status' => 'authorized',
        ], 200)]);

        $this->postJson('/api/webhooks/mercadopago', ['data' => ['id' => 'mp-sub-456']])
            ->assertStatus(200);

        $this->assertDatabaseHas('plano_selecionado', [
            'id_usuario' => $user->id, 'id_plano' => $gratuito->id, 'status' => 0,
        ]);
        $this->assertDatabaseHas('plano_selecionado', ['id' => $selecao->id, 'status' => 1]);
    }

    public function test_webhook_com_assinatura_ainda_pendente_nao_ativa_plano(): void
    {
        $user = $this->clientUser();
        $premium = Plano::where('name_plano', 'Premium')->firstOrFail();
        $selecao = PlanoSelecionado::create([
            'id_usuario' => $user->id, 'id_plano' => $premium->id,
            'status' => 0, 'mp_subscription_id' => 'mp-sub-789',
        ]);

        Http::fake(['*/preapproval/mp-sub-789' => Http::response([
            'id' => 'mp-sub-789', 'status' => 'pending',
        ], 200)]);

        $this->postJson('/api/webhooks/mercadopago', ['data' => ['id' => 'mp-sub-789']])
            ->assertStatus(200);

        $this->assertDatabaseHas('plano_selecionado', ['id' => $selecao->id, 'status' => 0]);
        $this->assertNull($user->fresh()->planoAtivo());
    }

    public function test_webhook_com_assinatura_desconhecida_nao_falha(): void
    {
        Http::fake(); // não deveria nem chamar buscarAssinatura

        $this->postJson('/api/webhooks/mercadopago', ['data' => ['id' => 'nao-existe']])
            ->assertStatus(200);

        Http::assertNothingSent();
    }

    public function test_webhook_com_assinatura_hmac_invalida_e_rejeitado(): void
    {
        config(['services.mercadopago.webhook_secret' => 'segredo-de-teste']);

        $response = $this->postJson('/api/webhooks/mercadopago', ['data' => ['id' => 'mp-sub-123']], [
            'x-signature' => 'ts=123,v1=assinatura-forjada',
            'x-request-id' => 'req-1',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_com_assinatura_hmac_valida_e_aceito(): void
    {
        config(['services.mercadopago.webhook_secret' => 'segredo-de-teste']);
        $user = $this->clientUser();
        $premium = Plano::where('name_plano', 'Premium')->firstOrFail();
        PlanoSelecionado::create([
            'id_usuario' => $user->id, 'id_plano' => $premium->id,
            'status' => 0, 'mp_subscription_id' => 'mp-sub-999',
        ]);

        Http::fake(['*/preapproval/mp-sub-999' => Http::response([
            'id' => 'mp-sub-999', 'status' => 'authorized',
        ], 200)]);

        $ts = (string) time();
        $requestId = 'req-válido';
        $manifest = "id:mp-sub-999;request-id:{$requestId};ts:{$ts};";
        $v1 = hash_hmac('sha256', $manifest, 'segredo-de-teste');

        $response = $this->postJson('/api/webhooks/mercadopago?data_id=mp-sub-999', ['data' => ['id' => 'mp-sub-999']], [
            'x-signature' => "ts={$ts},v1={$v1}",
            'x-request-id' => $requestId,
        ]);

        $response->assertStatus(200);
        $this->assertSame($premium->id, $user->fresh()->planoAtivo()->id);
    }
}
