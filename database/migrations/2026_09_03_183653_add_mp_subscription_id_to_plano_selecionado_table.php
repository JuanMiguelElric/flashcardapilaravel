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
        Schema::table('plano_selecionado', function (Blueprint $table) {
            $table->string('mp_subscription_id')->nullable()->after('id_plano')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plano_selecionado', function (Blueprint $table) {
            $table->dropColumn('mp_subscription_id');
        });
    }
};
