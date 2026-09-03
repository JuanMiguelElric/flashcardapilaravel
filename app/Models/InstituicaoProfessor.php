<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstituicaoProfessor extends Model
{
    protected $table = 'instituicao_professores';

    protected $fillable = ['instituicao_id', 'user_id', 'status', 'convidado_em', 'aceito_em'];

    protected function casts(): array
    {
        return [
            'convidado_em' => 'datetime',
            'aceito_em' => 'datetime',
        ];
    }

    public function instituicao(): BelongsTo
    {
        return $this->belongsTo(Instituicao::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
