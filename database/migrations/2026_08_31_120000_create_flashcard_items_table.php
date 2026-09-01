<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identidade canônica de cada flashcard individual.
     *
     * O conteúdo (pergunta, resposta, opções, etc.) continua vivendo no
     * Neo4j via microsserviço Python. Esta tabela existe apenas para dar
     * a cada flashcard um ID estável, gerado pelo MySQL, que é enviado ao
     * Python como `flashcard_id` e usado como chave de identidade real
     * (nunca o título/pergunta, que pode se repetir entre usuários).
     *
     * A tabela `flashcard` pré-existente é um contador por
     * (user_id, categoria_id) e não representa cards individuais; foi
     * mantida intacta (sem uso no novo fluxo) para não descartar dados.
     */
    public function up(): void
    {
        Schema::create('flashcard_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->string('type');
            $table->timestamps();

            $table->index(['user_id', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcard_items');
    }
};
