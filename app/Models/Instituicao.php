<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instituicao extends Model
{
    protected $table = 'instituicoes';

    protected $fillable = ['nome', 'owner_user_id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function professores(): HasMany
    {
        return $this->hasMany(InstituicaoProfessor::class);
    }

    public function professoresAtivos(): HasMany
    {
        return $this->professores()->where('status', 'ativo');
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }
}
