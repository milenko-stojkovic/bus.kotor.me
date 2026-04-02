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
        Schema::table('temp_data', function (Blueprint $table) {
            if (! Schema::hasColumn('temp_data', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('merchant_transaction_id');
                $table->foreign('user_id', 'fk_temp_user')
                      ->references('id')->on('users')
                      ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temp_data', function (Blueprint $table) {
            if (Schema::hasColumn('temp_data', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};
