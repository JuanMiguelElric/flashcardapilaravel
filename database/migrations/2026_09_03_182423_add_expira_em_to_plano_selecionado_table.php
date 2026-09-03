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
     * expira_em nulo = sem data de expiracao (Gratuito, ou assinatura paga
     * sem cancelamento pendente). Preenchido com a proxima data de
     * cobranca ao ativar uma assinatura Mercado Pago; ao cancelar, o valor
     * ja gravado e preservado (acesso pago continua ate essa data - ver
     * User::planoSelecionado()).
     */
    public function up(): void
    {
        Schema::table('plano_selecionado', function (Blueprint $table) {
            $table->timestamp('expira_em')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plano_selecionado', function (Blueprint $table) {
            $table->dropColumn('expira_em');
        });
    }
};
