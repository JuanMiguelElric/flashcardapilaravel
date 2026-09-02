<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Nullable de propósito: não existe, em nenhum lugar do código ou
     * documentação, um valor real de limite por plano (ver PlanLimitService
     * e o relatório da Parte 2). NULL = sem limite aplicado - os valores
     * reais por tier são uma decisão de produto pendente, não inventada
     * aqui.
     */
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->integer('limite_flashcards')->nullable()->after('desconto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn('limite_flashcards');
        });
    }
};
