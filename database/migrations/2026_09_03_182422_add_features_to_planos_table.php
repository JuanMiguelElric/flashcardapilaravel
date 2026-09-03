<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->integer('limite_categorias')->nullable()->after('limite_flashcards');
            $table->boolean('permite_audio')->default(false)->after('limite_categorias');
            $table->boolean('permite_multipla_escolha')->default(false)->after('permite_audio');
            $table->boolean('estatisticas_avancadas')->default(false)->after('permite_multipla_escolha');
            $table->integer('max_alunos')->nullable()->after('estatisticas_avancadas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn(['limite_categorias', 'permite_audio', 'permite_multipla_escolha', 'estatisticas_avancadas', 'max_alunos']);
        });
    }
};
