<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plano extends Model
{
    protected $table = "planos";
    protected $fillable = [
        "name_plano", "Descricao", "valor", "desconto",
        "limite_flashcards", "limite_categorias",
        "permite_audio", "permite_multipla_escolha",
        "estatisticas_avancadas", "max_alunos",
    ];

    protected function casts(): array
    {
        return [
            'permite_audio' => 'boolean',
            'permite_multipla_escolha' => 'boolean',
            'estatisticas_avancadas' => 'boolean',
        ];
    }
}
