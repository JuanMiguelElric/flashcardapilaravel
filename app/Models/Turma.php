<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turma extends Model
{
    protected $fillable = ['instituicao_id', 'professor_user_id', 'nome'];

    public function instituicao(): BelongsTo
    {
        return $this->belongsTo(Instituicao::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professor_user_id');
    }

    public function alunos(): HasMany
    {
        return $this->hasMany(TurmaAluno::class);
    }

    public function alunosAtivos(): HasMany
    {
        return $this->alunos()->where('status', 'ativo');
    }
}
