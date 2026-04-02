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
            // SQLite doesn't support ALTER TABLE ... MODIFY, and this migration is MySQL-specific.
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

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
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        DB::statement('ALTER TABLE vehicles MODIFY user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_vehicles_user')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }
};
