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

    public function planoSelecionado(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlanoSelecionado::class, 'id_usuario')->where('status', 1);
    }

    /**
     * Plano ativo do usuário, ou null se nenhum foi selecionado
     * (comportamento padrão hoje: nenhum usuário tem plano_selecionado
     * até chamar POST /plano/selecionar - sem isso, PlanLimitService não
     * aplica nenhum limite).
     */
    public function planoAtivo(): ?Plano
    {
        return $this->planoSelecionado?->plano;
    }
}