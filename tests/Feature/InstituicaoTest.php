<?php

namespace Tests\Feature;

use App\Models\Instituicao;
use App\Models\InstituicaoProfessor;
use App\Models\Plano;
use App\Models\PlanoSelecionado;
use App\Models\Turma;
use App\Models\TurmaAluno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstituicaoTest extends TestCase
{
    use RefreshDatabase;

    private function clientUser(): User
    {
        return User::factory()->create(['role' => 'client']);
    }

    private function ativarInstitucional(User $user, ?int $maxAlunos = 50): void
    {
        $plano = Plano::firstOrCreate(
            ['name_plano' => 'Institucional'],
            ['Descricao' => 'x', 'valor' => 99, 'desconto' => 0, 'max_alunos' => $maxAlunos]
        );
        // Garante o max_alunos deste teste específico mesmo que o plano
        // Institucional já exista via seed_planos_oficiais.
        $plano->update(['max_alunos' => $maxAlunos]);
        PlanoSelecionado::create(['id_usuario' => $user->id, 'id_plano' => $plano->id, 'status' => 1]);
    }

    public function test_criar_instituicao_exige_plano_institucional_ativo(): void
    {
        $user = $this->clientUser(); // sem plano ativo -> fallback Gratuito

        $this->actingAs($user)->postJson('/api/instituicao', ['nome' => 'Escola X'])
            ->assertStatus(422);
    }

    public function test_criar_instituicao_com_plano_institucional(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);

        $response = $this->actingAs($owner)->postJson('/api/instituicao', ['nome' => 'Escola X']);

        $response->assertStatus(201)->assertJson(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
    }

    public function test_owner_ja_nasce_professor_ativo_e_pode_criar_turma_direto(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);

        $this->assertDatabaseMissing('instituicao_professores', [
            'instituicao_id' => $instituicao->id, 'user_id' => $owner->id,
        ]);

        // Recriando via o service real (não direto no model, como as
        // fixtures acima) para exercitar o comportamento de
        // InstituicaoService::criar.
        $response = $this->actingAs($owner)->postJson('/api/instituicao', ['nome' => 'Escola Y']);
        $novaInstituicao = $response->json();

        $this->assertDatabaseHas('instituicao_professores', [
            'instituicao_id' => $novaInstituicao['id'], 'user_id' => $owner->id, 'status' => 'ativo',
        ]);

        $this->actingAs($owner)->postJson("/api/instituicao/{$novaInstituicao['id']}/turmas", [
            'nome' => 'Turma A',
        ])->assertStatus(201);
    }

    public function test_lista_instituicoes_onde_e_professor_ativo(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $this->actingAs($owner)->postJson('/api/instituicao', ['nome' => 'Escola X'])->assertStatus(201);

        $outroUsuario = $this->clientUser();

        $this->actingAs($owner)->getJson('/api/instituicao')
            ->assertStatus(200)->assertJsonCount(1);
        $this->actingAs($outroUsuario)->getJson('/api/instituicao')
            ->assertStatus(200)->assertJsonCount(0);
    }

    public function test_owner_convida_professor_existente(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
        $professor = $this->clientUser();

        $response = $this->actingAs($owner)->postJson("/api/instituicao/{$instituicao->id}/professores", [
            'email' => $professor->email,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('instituicao_professores', [
            'instituicao_id' => $instituicao->id, 'user_id' => $professor->id, 'status' => 'pendente',
        ]);
    }

    public function test_convidar_professor_com_email_inexistente_falha(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);

        $this->actingAs($owner)->postJson("/api/instituicao/{$instituicao->id}/professores", [
            'email' => 'ninguem@example.com',
        ])->assertStatus(422);
    }

    public function test_apenas_owner_convida_professor(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
        $outroUsuario = $this->clientUser();
        $professor = $this->clientUser();

        $this->actingAs($outroUsuario)->postJson("/api/instituicao/{$instituicao->id}/professores", [
            'email' => $professor->email,
        ])->assertStatus(403);
    }

    public function test_professor_aceita_convite_e_cria_turma(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
        $professor = $this->clientUser();
        $convite = InstituicaoProfessor::create([
            'instituicao_id' => $instituicao->id, 'user_id' => $professor->id, 'status' => 'pendente',
        ]);

        $this->actingAs($professor)->postJson("/api/convites/professor/{$convite->id}/aceitar")
            ->assertStatus(200);
        $this->assertDatabaseHas('instituicao_professores', ['id' => $convite->id, 'status' => 'ativo']);

        $response = $this->actingAs($professor)->postJson("/api/instituicao/{$instituicao->id}/turmas", [
            'nome' => 'Turma A',
        ]);

        $response->assertStatus(201)->assertJson(['nome' => 'Turma A', 'professor_user_id' => $professor->id]);
    }

    public function test_professor_nao_ativo_nao_cria_turma(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
        $naoConvidado = $this->clientUser();

        $this->actingAs($naoConvidado)->postJson("/api/instituicao/{$instituicao->id}/turmas", [
            'nome' => 'Turma A',
        ])->assertStatus(403);
    }

    public function test_outro_convite_de_professor_nao_pode_ser_aceito_por_outro_usuario(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
        $professor = $this->clientUser();
        $outroUsuario = $this->clientUser();
        $convite = InstituicaoProfessor::create([
            'instituicao_id' => $instituicao->id, 'user_id' => $professor->id, 'status' => 'pendente',
        ]);

        $this->actingAs($outroUsuario)->postJson("/api/convites/professor/{$convite->id}/aceitar")
            ->assertStatus(403);
    }

    private function professorAtivoComTurma(Instituicao $instituicao): array
    {
        $professor = $this->clientUser();
        InstituicaoProfessor::create([
            'instituicao_id' => $instituicao->id, 'user_id' => $professor->id, 'status' => 'ativo', 'aceito_em' => now(),
        ]);
        $turma = Turma::create([
            'instituicao_id' => $instituicao->id, 'professor_user_id' => $professor->id, 'nome' => 'Turma A',
        ]);

        return [$professor, $turma];
    }

    public function test_professor_convida_aluno_existente_para_sua_turma(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
        [$professor, $turma] = $this->professorAtivoComTurma($instituicao);
        $aluno = $this->clientUser();

        $response = $this->actingAs($professor)->postJson("/api/turma/{$turma->id}/alunos", [
            'email' => $aluno->email,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('turma_alunos', [
            'turma_id' => $turma->id, 'aluno_user_id' => $aluno->id, 'status' => 'pendente',
        ]);
    }

    public function test_professor_nao_convida_aluno_para_turma_de_outro_professor(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
        [, $turma] = $this->professorAtivoComTurma($instituicao);
        [$outroProfessor] = $this->professorAtivoComTurma($instituicao);
        $aluno = $this->clientUser();

        $this->actingAs($outroProfessor)->postJson("/api/turma/{$turma->id}/alunos", [
            'email' => $aluno->email,
        ])->assertStatus(403);
    }

    public function test_limite_de_alunos_por_instituicao_bloqueia_novo_convite(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner, maxAlunos: 1);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
        [$professor, $turma] = $this->professorAtivoComTurma($instituicao);
        $alunoJaAtivo = $this->clientUser();
        TurmaAluno::create([
            'turma_id' => $turma->id, 'aluno_user_id' => $alunoJaAtivo->id, 'status' => 'ativo', 'aceito_em' => now(),
        ]);
        $novoAluno = $this->clientUser();

        $this->actingAs($professor)->postJson("/api/turma/{$turma->id}/alunos", [
            'email' => $novoAluno->email,
        ])->assertStatus(422);
    }

    public function test_professor_lista_alunos_da_propria_turma(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
        [$professor, $turma] = $this->professorAtivoComTurma($instituicao);
        $aluno = $this->clientUser();
        TurmaAluno::create([
            'turma_id' => $turma->id, 'aluno_user_id' => $aluno->id, 'status' => 'ativo', 'aceito_em' => now(),
        ]);

        $response = $this->actingAs($professor)->getJson("/api/turma/{$turma->id}/alunos");

        $response->assertStatus(200)->assertJsonCount(1);
        $this->assertSame($aluno->id, $response->json('0.aluno.id'));
    }

    public function test_professor_nao_lista_alunos_de_turma_alheia(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
        [, $turma] = $this->professorAtivoComTurma($instituicao);
        [$outroProfessor] = $this->professorAtivoComTurma($instituicao);

        $this->actingAs($outroProfessor)->getJson("/api/turma/{$turma->id}/alunos")->assertStatus(403);
    }

    public function test_aluno_aceita_convite_e_professor_ve_relatorio_real(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
        [$professor, $turma] = $this->professorAtivoComTurma($instituicao);
        $aluno = $this->clientUser();
        $convite = TurmaAluno::create([
            'turma_id' => $turma->id, 'aluno_user_id' => $aluno->id, 'status' => 'pendente',
        ]);

        $this->actingAs($aluno)->postJson("/api/convites/aluno/{$convite->id}/aceitar")->assertStatus(200);

        $response = $this->actingAs($professor)->getJson("/api/turma/{$turma->id}/alunos/{$aluno->id}/relatorio");

        $response->assertStatus(200)->assertJsonStructure([
            'total_flashcards', 'total_categorias', 'flashcards_by_categoria', 'flashcards_ultimos_7_dias',
        ]);
    }

    public function test_relatorio_de_aluno_nao_matriculado_e_negado(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
        [$professor, $turma] = $this->professorAtivoComTurma($instituicao);
        $usuarioQualquer = $this->clientUser();

        $this->actingAs($professor)->getJson("/api/turma/{$turma->id}/alunos/{$usuarioQualquer->id}/relatorio")
            ->assertStatus(403);
    }

    public function test_relatorio_negado_para_professor_que_nao_e_dono_da_turma(): void
    {
        $owner = $this->clientUser();
        $this->ativarInstitucional($owner);
        $instituicao = Instituicao::create(['nome' => 'Escola X', 'owner_user_id' => $owner->id]);
        [$professor, $turma] = $this->professorAtivoComTurma($instituicao);
        [$outroProfessor] = $this->professorAtivoComTurma($instituicao);
        $aluno = $this->clientUser();
        TurmaAluno::create([
            'turma_id' => $turma->id, 'aluno_user_id' => $aluno->id, 'status' => 'ativo', 'aceito_em' => now(),
        ]);

        $this->actingAs($outroProfessor)->getJson("/api/turma/{$turma->id}/alunos/{$aluno->id}/relatorio")
            ->assertStatus(403);
    }
}
