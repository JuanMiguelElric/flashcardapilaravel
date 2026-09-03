<?php

namespace App\Services;

use App\Models\Instituicao;
use App\Models\InstituicaoProfessor;
use App\Models\Turma;
use App\Models\TurmaAluno;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Orquestra o domínio Institucional: instituição -> professores (vários
 * por instituição) -> turmas -> alunos (contas reais, vinculadas por
 * convite - nunca um roster manual sem login). Limite de "até 50 alunos"
 * é por instituição (soma de todas as turmas), não por turma.
 *
 * LIMITAÇÃO DOCUMENTADA: convidar alguém que ainda não tem conta não é
 * suportado (exigiria e-mail transacional/convite pendente-até-registro,
 * infra que não existe no projeto hoje) - o convite exige que o e-mail já
 * pertença a um usuário cadastrado.
 */
class InstituicaoService
{
    public function __construct(private PlanLimitService $planLimitService, private DashboardService $dashboardService) {}

    public function criar(User $owner, string $nome): Instituicao
    {
        $plano = $this->planLimitService->resolveActivePlano($owner);

        if ($plano->name_plano !== 'Institucional') {
            throw ValidationException::withMessages([
                'nome' => 'Criar uma instituição exige o plano Institucional ativo.',
            ]);
        }

        $instituicao = Instituicao::create(['nome' => $nome, 'owner_user_id' => $owner->id]);

        // Quem cria a instituição já nasce professor ativo dela - sem
        // isso, o próprio dono não conseguiria criar turmas sem passar
        // por um autoconvite (fluxo sem sentido para quem acabou de
        // pagar pelo plano Institucional).
        InstituicaoProfessor::create([
            'instituicao_id' => $instituicao->id,
            'user_id' => $owner->id,
            'status' => 'ativo',
            'aceito_em' => now(),
        ]);

        return $instituicao;
    }

    public function convidarProfessor(Instituicao $instituicao, User $owner, string $email): InstituicaoProfessor
    {
        $this->authorizeOwner($instituicao, $owner);

        $convidado = User::where('email', $email)->first();

        if (! $convidado) {
            throw ValidationException::withMessages([
                'email' => 'Nenhum usuário cadastrado com este e-mail. Peça para a pessoa criar uma conta antes de convidar.',
            ]);
        }

        return InstituicaoProfessor::firstOrCreate(
            ['instituicao_id' => $instituicao->id, 'user_id' => $convidado->id],
            ['status' => 'pendente', 'convidado_em' => now()]
        );
    }

    public function aceitarConviteProfessor(InstituicaoProfessor $convite, User $user): InstituicaoProfessor
    {
        if ((int) $convite->user_id !== (int) $user->id) {
            throw new HttpException(403, 'Este convite não é seu.');
        }

        $convite->update(['status' => 'ativo', 'aceito_em' => now()]);

        return $convite;
    }

    public function criarTurma(User $professor, Instituicao $instituicao, string $nome): Turma
    {
        $this->authorizeProfessorAtivo($instituicao, $professor);

        return Turma::create([
            'instituicao_id' => $instituicao->id,
            'professor_user_id' => $professor->id,
            'nome' => $nome,
        ]);
    }

    public function convidarAluno(Turma $turma, User $professor, string $email): TurmaAluno
    {
        $this->authorizeProfessorDaTurma($turma, $professor);

        $aluno = User::where('email', $email)->first();

        if (! $aluno) {
            throw ValidationException::withMessages([
                'email' => 'Nenhum usuário cadastrado com este e-mail. Peça para a pessoa criar uma conta antes de convidar.',
            ]);
        }

        $this->assertLimiteAlunosNaoExcedido($turma->instituicao);

        return TurmaAluno::firstOrCreate(
            ['turma_id' => $turma->id, 'aluno_user_id' => $aluno->id],
            ['status' => 'pendente', 'convidado_em' => now()]
        );
    }

    public function aceitarConviteAluno(TurmaAluno $convite, User $user): TurmaAluno
    {
        if ((int) $convite->aluno_user_id !== (int) $user->id) {
            throw new HttpException(403, 'Este convite não é seu.');
        }

        $convite->update(['status' => 'ativo', 'aceito_em' => now()]);

        return $convite;
    }

    /**
     * @return \Illuminate\Support\Collection<int, TurmaAluno>
     */
    public function listarAlunos(Turma $turma, User $professor): \Illuminate\Support\Collection
    {
        $this->authorizeProfessorDaTurma($turma, $professor);

        return $turma->alunos()->with('aluno:id,name,email')->get();
    }

    public function relatorioDoAluno(Turma $turma, User $professor, User $aluno): array
    {
        $this->authorizeProfessorDaTurma($turma, $professor);

        $matriculado = $turma->alunosAtivos()->where('aluno_user_id', $aluno->id)->exists();

        if (! $matriculado) {
            throw new HttpException(403, 'Este aluno não está matriculado (ativo) nesta turma.');
        }

        return $this->dashboardService->statsForUser($aluno);
    }

    private function authorizeOwner(Instituicao $instituicao, User $user): void
    {
        if ((int) $instituicao->owner_user_id !== (int) $user->id) {
            throw new HttpException(403, 'Você não tem permissão para gerenciar esta instituição.');
        }
    }

    private function authorizeProfessorAtivo(Instituicao $instituicao, User $professor): void
    {
        $ativo = $instituicao->professoresAtivos()->where('user_id', $professor->id)->exists();

        if (! $ativo) {
            throw new HttpException(403, 'Você não é um professor ativo desta instituição.');
        }
    }

    private function authorizeProfessorDaTurma(Turma $turma, User $professor): void
    {
        if ((int) $turma->professor_user_id !== (int) $professor->id) {
            throw new HttpException(403, 'Você não é o professor desta turma.');
        }
    }

    /**
     * "Até 50 alunos" é um atributo do plano (planos.max_alunos), somado
     * por TODAS as turmas da instituição (não por turma individual).
     */
    private function assertLimiteAlunosNaoExcedido(Instituicao $instituicao): void
    {
        $plano = $this->planLimitService->resolveActivePlano($instituicao->owner);

        if ($plano->max_alunos === null) {
            return;
        }

        $atual = TurmaAluno::whereIn('turma_id', $instituicao->turmas()->pluck('id'))
            ->where('status', 'ativo')
            ->count();

        if ($atual >= $plano->max_alunos) {
            throw ValidationException::withMessages([
                'email' => "Limite de {$plano->max_alunos} alunos desta instituição foi atingido.",
            ]);
        }
    }
}
