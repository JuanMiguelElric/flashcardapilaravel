<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * As migrations originais criaram categorias/flashcard/plano_selecionado
     * com colunas `integer` soltas para o que deveriam ser foreign keys,
     * sem nenhuma constraint de integridade referencial. Esta migration
     * corrige isso sem apagar dados: converte as colunas para o mesmo
     * tipo de `users.id`/`planos.id`/`categorias.id` (unsignedBigInteger,
     * via `$table->id()`) e então cria as constraints.
     *
     * Verificado antes de escrever esta migration: 0 linhas órfãs em
     * `categorias.user_id` e `flashcard.user_id` no ambiente de
     * desenvolvimento (ver relatório de auditoria).
     */
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->change();
        });
        Schema::table('categorias', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('flashcard', function (Blueprint $table) {
            $table->unsignedBigInteger('categoria_id')->change();
            $table->unsignedBigInteger('user_id')->change();
        });
        Schema::table('flashcard', function (Blueprint $table) {
            $table->foreign('categoria_id')->references('id')->on('categorias')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('plano_selecionado', function (Blueprint $table) {
            $table->unsignedBigInteger('id_usuario')->change();
            $table->unsignedBigInteger('id_plano')->change();
        });
        Schema::table('plano_selecionado', function (Blueprint $table) {
            $table->foreign('id_usuario')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('id_plano')->references('id')->on('planos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('flashcard', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('plano_selecionado', function (Blueprint $table) {
            $table->dropForeign(['id_usuario']);
            $table->dropForeign(['id_plano']);
        });
    }
};
