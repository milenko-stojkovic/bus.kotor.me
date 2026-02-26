<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * retry_token: unique token for guest retry after failed/canceled payment.
     * Used in GET /api/reservations/retry/{retry_token} and redirect /reservations?retry_token=...
     */
    public function up(): void
    {
        Schema::table('temp_data', function (Blueprint $table) {
            $table->string('retry_token', 36)->nullable()->unique()->after('merchant_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('temp_data', function (Blueprint $table) {
            $table->dropColumn('retry_token');
        });
    }
};
