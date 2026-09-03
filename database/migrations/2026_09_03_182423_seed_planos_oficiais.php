<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fonte de verdade dos 3 planos oficiais - valores exatos da
 * especificacao comercial (nao inventados). limite_flashcards/
 * limite_categorias nulos = ilimitado; max_alunos so existe no
 * Institucional.
 */
return new class extends Migration
{
    private const PLANOS = [
        [
            'name_plano' => 'Gratuito',
            'Descricao' => '50 flashcards por mes, 3 categorias/materias, flashcards de texto, estatisticas basicas.',
            'valor' => 0,
            'desconto' => 0,
            'limite_flashcards' => 50,
            'limite_categorias' => 3,
            'permite_audio' => false,
            'permite_multipla_escolha' => false,
            'estatisticas_avancadas' => false,
            'max_alunos' => null,
        ],
        [
            'name_plano' => 'Premium',
            'Descricao' => 'Flashcards e categorias ilimitados, audio, multipla escolha, estatisticas avancadas, missoes exclusivas, premios mensais, suporte prioritario.',
            'valor' => 29,
            'desconto' => 0,
            'limite_flashcards' => null,
            'limite_categorias' => null,
            'permite_audio' => true,
            'permite_multipla_escolha' => true,
            'estatisticas_avancadas' => true,
            'max_alunos' => null,
        ],
        [
            'name_plano' => 'Institucional',
            'Descricao' => 'Tudo do Premium, ate 50 alunos, painel do professor, relatorios de progresso, criacao de turmas, treinamento incluso.',
            'valor' => 99,
            'desconto' => 0,
            'limite_flashcards' => null,
            'limite_categorias' => null,
            'permite_audio' => true,
            'permite_multipla_escolha' => true,
            'estatisticas_avancadas' => true,
            'max_alunos' => 50,
        ],
    ];

    public function up(): void
    {
        foreach (self::PLANOS as $plano) {
            if (! DB::table('planos')->where('name_plano', $plano['name_plano'])->exists()) {
                DB::table('planos')->insert(array_merge($plano, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('planos')->whereIn('name_plano', array_column(self::PLANOS, 'name_plano'))->delete();
    }
};
