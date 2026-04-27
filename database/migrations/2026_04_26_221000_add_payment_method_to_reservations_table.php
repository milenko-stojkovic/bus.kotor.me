<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'payment_method')) {
                $table->string('payment_method', 32)->nullable()->after('merchant_transaction_id');
                $table->index('payment_method', 'idx_res_payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'payment_method')) {
                $table->dropIndex('idx_res_payment_method');
                $table->dropColumn('payment_method');
            }
        });
    }
};

