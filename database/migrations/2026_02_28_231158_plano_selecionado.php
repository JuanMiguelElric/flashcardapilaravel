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
        //
        Schema::create("plano_selecionado", function (Blueprint $table) {
            $table->id();
            $table->integer("id_usuario");
            $table->integer("id_plano");
            $table->float("status")->default(0);//0=>inativo,1=>ativo
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
