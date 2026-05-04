<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('limo_pickup_events', function (Blueprint $table) {
            $table->string('merchant_transaction_id', 64)->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('limo_pickup_events', function (Blueprint $table) {
            $table->dropUnique(['merchant_transaction_id']);
            $table->dropColumn('merchant_transaction_id');
        });
    }
};
