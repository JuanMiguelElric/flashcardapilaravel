<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurmaAluno extends Model
{
    protected $fillable = ['turma_id', 'aluno_user_id', 'status', 'convidado_em', 'aceito_em'];

    protected function casts(): array
    {
        return [
            'convidado_em' => 'datetime',
            'aceito_em' => 'datetime',
        ];
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aluno_user_id');
    }
}
