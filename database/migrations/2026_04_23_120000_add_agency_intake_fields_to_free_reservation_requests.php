<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('free_reservation_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->string('institution_phone', 32)->nullable()->change();

            $table->index('user_id', 'idx_frr_user_id');
            $table->foreign('user_id', 'fk_frr_user')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('free_reservation_requests', function (Blueprint $table) {
            $table->dropForeign('fk_frr_user');
            $table->dropIndex('idx_frr_user_id');
            $table->dropColumn('user_id');
            $table->string('institution_phone', 32)->nullable(false)->change();
        });
    }
};

