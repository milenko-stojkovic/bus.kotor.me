<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Payment state machine: pending | processed | late_success | canceled | expired.
     * Migrate existing 'failed' to 'canceled'.
     */
    public function up(): void
    {
        DB::table('temp_data')->where('status', 'failed')->update(['status' => 'canceled']);
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE temp_data MODIFY COLUMN status ENUM('pending', 'processed', 'late_success', 'canceled', 'expired') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        DB::table('temp_data')->whereIn('status', ['canceled', 'expired'])->update(['status' => 'failed']);
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE temp_data MODIFY COLUMN status ENUM('pending', 'failed', 'late_success', 'processed') DEFAULT 'pending'");
        }
    }
};
