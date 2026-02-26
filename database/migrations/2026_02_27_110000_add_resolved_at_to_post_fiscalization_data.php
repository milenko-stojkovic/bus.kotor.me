<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin "mark as resolved" – cron preskače redove gde je resolved_at set.
     */
    public function up(): void
    {
        Schema::table('post_fiscalization_data', function (Blueprint $table) {
            $table->timestamp('resolved_at')->nullable()->after('next_retry_at');
        });
    }

    public function down(): void
    {
        Schema::table('post_fiscalization_data', function (Blueprint $table) {
            $table->dropColumn('resolved_at');
        });
    }
};
