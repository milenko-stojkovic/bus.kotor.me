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
        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->index('user_id', 'idx_res_user');
                $table->foreign('user_id', 'fk_res_user')
                      ->references('id')->on('users')
                      ->onDelete('set null');
            }
            if (! Schema::hasColumn('reservations', 'vehicle_id')) {
                $table->unsignedInteger('vehicle_id')->nullable()->after('user_id');
                $table->foreign('vehicle_id', 'fk_res_vehicle_v2')
                      ->references('id')->on('vehicles')
                      ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropIndex('idx_res_user');
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('reservations', 'vehicle_id')) {
                $table->dropForeign(['vehicle_id']);
                $table->dropColumn('vehicle_id');
            }
        });
    }
};
