<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fiscal retry pipeline: error, attempts, next_retry_at for post_fiscalization_data.
     */
    public function up(): void
    {
        Schema::table('post_fiscalization_data', function (Blueprint $table) {
            $table->text('error')->nullable()->after('merchant_transaction_id');
            $table->unsignedTinyInteger('attempts')->default(1)->after('error');
            $table->timestamp('next_retry_at')->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('post_fiscalization_data', function (Blueprint $table) {
            $table->dropColumn(['error', 'attempts', 'next_retry_at']);
        });
    }
};
