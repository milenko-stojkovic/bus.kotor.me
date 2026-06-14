<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('free_reservation_request_id')->nullable()->after('user_id');
            $table->index('free_reservation_request_id', 'idx_res_frr_id');
            $table->foreign('free_reservation_request_id', 'fk_res_frr')
                ->references('id')
                ->on('free_reservation_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign('fk_res_frr');
            $table->dropIndex('idx_res_frr_id');
            $table->dropColumn('free_reservation_request_id');
        });
    }
};
