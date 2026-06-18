<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('free_reservation_requests') || ! Schema::hasColumn('free_reservation_requests', 'user_id')) {
            return;
        }

        Schema::table('free_reservation_requests', function (Blueprint $table) {
            $table->dropForeign('fk_frr_user');
            $table->foreign('user_id', 'fk_frr_user')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('free_reservation_requests') || ! Schema::hasColumn('free_reservation_requests', 'user_id')) {
            return;
        }

        Schema::table('free_reservation_requests', function (Blueprint $table) {
            $table->dropForeign('fk_frr_user');
            $table->foreign('user_id', 'fk_frr_user')
                ->references('id')
                ->on('users');
        });
    }
};
