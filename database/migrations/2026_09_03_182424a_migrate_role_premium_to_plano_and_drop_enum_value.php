<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * role deixa de ter 'premium' - vira so admin/client (decisao de
 * produto: autorizacao e comercial sao conceitos separados, ver
 * User::planoAtivo()). Usuarios com role=premium sao migrados para
 * role=client + plano Premium ativo antes do enum ser reduzido, para
 * nao perder o beneficio que ja tinham.
 *
 * doctrine/dbal nao esta instalado, e ->change() do Schema builder nao
 * e confiavel para enum MySQL - ALTER TABLE raw e o caminho usado no
 * projeto (ver add_foreign_keys_to_legacy_tables.php para outro
 * ->change() sem doctrine/dbal, em coluna nao-enum).
 */
return new class extends Migration
{
    public function up(): void
    {
        $premiumPlanoId = DB::table('planos')->where('name_plano', 'Premium')->value('id');

        DB::table('users')->where('role', 'premium')->orderBy('id')->get()->each(function ($user) use ($premiumPlanoId) {
            $temPlanoAtivo = DB::table('plano_selecionado')
                ->where('id_usuario', $user->id)
                ->where('status', 1)
                ->exists();

            if (! $temPlanoAtivo) {
                DB::table('plano_selecionado')->insert([
                    'id_usuario' => $user->id,
                    'id_plano' => $premiumPlanoId,
                    'status' => 1,
                    'expira_em' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        DB::table('users')->where('role', 'premium')->update(['role' => 'client']);

        // ALTER TABLE ... MODIFY COLUMN e sintaxe MySQL - o ambiente de
        // teste roda em SQLite (phpunit.xml), onde enum vira um CHECK
        // constraint que so pode ser alterado recriando a tabela. A
        // aplicacao ja garante em nivel de codigo que 'premium' nunca mais
        // e escrito (nenhum caminho atribui esse valor); o ALTER e so
        // reforco de schema no driver que realmente o suporta.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','client') NOT NULL DEFAULT 'client'");
        }
    }

    /**
     * Reverte o enum (permite 'premium' novamente) em MySQL, mas nao
     * desfaz a migracao de dados (usuarios ja migrados continuam client +
     * Plano Premium) - limitacao documentada, mesmo padrao ja aceito em
     * outras migrations deste projeto.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','client','premium') NOT NULL DEFAULT 'client'");
        }
    }
};
