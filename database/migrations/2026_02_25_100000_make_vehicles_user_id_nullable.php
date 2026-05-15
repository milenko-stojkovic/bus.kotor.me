<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * user_id nullable za guest-vozila (rezervacije bez naloga). FK onDelete set null.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Guard: SQLite — nema pouzdanog MODIFY + dropForeign isto kao MySQL; produkcija je MySQL.
            return;
        }

        $this->dropMysqlMariaDbVehiclesUserIdForeignKeys();

        DB::statement('ALTER TABLE vehicles MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_vehicles_user')
                ->references('id')->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Guard: vidi up().
            return;
        }

        $this->dropMysqlMariaDbVehiclesUserIdForeignKeys();

        DB::statement('ALTER TABLE vehicles MODIFY user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_vehicles_user')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Drop whatever FOREIGN KEY MySQL/MariaDB has on vehicles.user_id.
     *
     * {@see 2026_02_24_084343_create_vehicles_table} names the key `fk_vehicles_user`, but
     * {@see Blueprint::dropForeign} with a column array assumes `vehicles_user_id_foreign`,
     * which breaks migrate on clean MySQL (error 1091).
     */
    protected function dropMysqlMariaDbVehiclesUserIdForeignKeys(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $schema = DB::connection()->getDatabaseName();
        $rows = DB::select(
            'SELECT DISTINCT k.CONSTRAINT_NAME AS name
             FROM information_schema.KEY_COLUMN_USAGE k
             INNER JOIN information_schema.TABLE_CONSTRAINTS t
               ON k.TABLE_SCHEMA = t.TABLE_SCHEMA
               AND k.CONSTRAINT_NAME = t.CONSTRAINT_NAME
             WHERE k.TABLE_SCHEMA = ?
               AND k.TABLE_NAME = ?
               AND k.COLUMN_NAME = ?
               AND k.REFERENCED_TABLE_NAME IS NOT NULL
               AND t.CONSTRAINT_TYPE = ?
             ORDER BY k.CONSTRAINT_NAME',
            [$schema, 'vehicles', 'user_id', 'FOREIGN KEY']
        );

        foreach ($rows as $row) {
            $constraint = (string) $row->name;
            if ($constraint === '') {
                continue;
            }
            $escaped = str_replace('`', '``', $constraint);
            DB::statement('ALTER TABLE `vehicles` DROP FOREIGN KEY `'.$escaped.'`');
        }
    }
};
