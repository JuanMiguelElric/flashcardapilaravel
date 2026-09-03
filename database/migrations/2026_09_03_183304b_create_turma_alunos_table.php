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
        Schema::create('turma_alunos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('aluno_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pendente', 'ativo'])->default('pendente');
            $table->timestamp('convidado_em')->nullable();
            $table->timestamp('aceito_em')->nullable();
            $table->timestamps();

            $table->unique(['turma_id', 'aluno_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turma_alunos');
    }
};
