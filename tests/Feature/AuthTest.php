<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_com_credenciais_validas_retorna_token_e_role(): void
    {
        $user = User::factory()->create([
            'email' => 'joao@example.com',
            'password' => Hash::make('senha123'),
            'role' => 'client',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'joao@example.com',
            'password' => 'senha123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'role'])
            ->assertJson(['role' => 'client']);
    }

    public function test_login_com_senha_invalida_retorna_401(): void
    {
        User::factory()->create([
            'email' => 'joao@example.com',
            'password' => Hash::make('senha123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'joao@example.com',
            'password' => 'senha-errada',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_com_email_inexistente_retorna_401(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'ninguem@example.com',
            'password' => 'qualquer',
        ]);

        $response->assertStatus(401);
    }

    public function test_register_cria_usuario_com_role_client_por_padrao(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Maria',
            'email' => 'maria@example.com',
            'password' => 'senha123',
            'cpassword' => 'senha123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'name', 'role'])
            ->assertJson(['name' => 'Maria', 'role' => 'client']);

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
            'role' => 'client',
        ]);
    }

    public function test_register_com_confirmacao_de_senha_diferente_retorna_422(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Maria',
            'email' => 'maria@example.com',
            'password' => 'senha123',
            'cpassword' => 'outra-senha',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'maria@example.com']);
    }

    public function test_register_com_email_duplicado_retorna_422(): void
    {
        User::factory()->create(['email' => 'maria@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Maria',
            'email' => 'maria@example.com',
            'password' => 'senha123',
            'cpassword' => 'senha123',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Regressão do bug corrigido: /me não pode mais estar preso a
     * role:client - qualquer usuário autenticado precisa acessá-lo.
     */
    public function test_me_funciona_para_qualquer_role_autenticado(): void
    {
        foreach (['client', 'premium', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)->getJson('/api/me');

            $response->assertStatus(200)
                ->assertJsonPath('user.id', $user->id);
        }
    }

    public function test_me_sem_autenticacao_retorna_401(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_logout_revoga_todos_os_tokens_do_usuario(): void
    {
        $user = User::factory()->create();
        $user->createToken('teste');

        $this->assertSame(1, $user->tokens()->count());

        $this->actingAs($user)->postJson('/api/logout')->assertStatus(200);

        $this->assertSame(0, $user->tokens()->count());
    }
}
