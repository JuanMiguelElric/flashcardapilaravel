<?php


namespace App\Models;


// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable,HasApiTokens;


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }

    public function flashcardItems(): HasMany
    {
        return $this->hasMany(FlashcardItem::class);
    }

    /**
     * Ativo (status=1) e ainda não expirado - uma assinatura cancelada
     * mantém status=1 até expira_em (decisão de produto: acesso pago
     * continua até o fim do período já pago), depois deixa de casar aqui
     * e o usuário volta a não ter plano ativo (PlanLimitService::
     * resolveActivePlano cai para o Gratuito nesse caso).
     */
    public function planoSelecionado(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlanoSelecionado::class, 'id_usuario')
            ->where('status', 1)
            ->where(fn ($q) => $q->whereNull('expira_em')->orWhere('expira_em', '>', now()));
    }

    /**
     * Plano ativo do usuário, ou null se nenhum foi selecionado/expirou.
     * Desde seed_planos_oficiais + backfill_plano_gratuito_para_usuarios_
     * sem_plano, todo usuário real tem um plano ativo por padrão (ver
     * AuthController::register); use PlanLimitService::resolveActivePlano
     * quando precisar de um fallback garantido em vez de null.
     */
    public function planoAtivo(): ?Plano
    {
        return $this->planoSelecionado?->plano;
    }

    public function instituicoesQuePossui(): HasMany
    {
        return $this->hasMany(Instituicao::class, 'owner_user_id');
    }

    public function turmasQueLeciona(): HasMany
    {
        return $this->hasMany(Turma::class, 'professor_user_id');
    }

    public function turmasQueEstuda(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            Turma::class,
            TurmaAluno::class,
            'aluno_user_id', // FK em turma_alunos que aponta pra este User
            'id', // PK em turmas
            'id', // PK local (users.id)
            'turma_id' // FK em turma_alunos que aponta pra Turma
        )->where('turma_alunos.status', 'ativo');
    }
}