<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Garante que todo usuario tenha um plano ativo a partir daqui - os
 * novos gates de categoria/tipo de flashcard (PlanLimitService) passam a
 * depender de User::planoAtivo() nunca ficar "vazio por acidente" para
 * quem ja tinha conta antes desta mudanca. Roda DEPOIS da migracao de
 * role=premium (essa ja tera dado Premium a quem tinha direito) - so
 * sobra quem realmente nunca escolheu nada, que recebe Gratuito.
 */
return new class extends Migration
{
    public function up(): void
    {
        $gratuitoPlanoId = DB::table('planos')->where('name_plano', 'Gratuito')->value('id');

        $usuariosComPlanoAtivo = DB::table('plano_selecionado')
            ->where('status', 1)
            ->pluck('id_usuario');

        DB::table('users')
            ->whereNotIn('id', $usuariosComPlanoAtivo)
            ->orderBy('id')
            ->get()
            ->each(function ($user) use ($gratuitoPlanoId) {
                DB::table('plano_selecionado')->insert([
                    'id_usuario' => $user->id,
                    'id_plano' => $gratuitoPlanoId,
                    'status' => 1,
                    'expira_em' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Nao reversivel de forma segura (nao da para distinguir quem foi
     * backfillado aqui de quem selecionou Gratuito manualmente depois) -
     * down() e um no-op deliberado, mesmo padrao de outras migrations de
     * dados neste projeto.
     */
    public function down(): void
    {
        //
    }
};
