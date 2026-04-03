<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temp_data', function (Blueprint $table) {
            $table->string('resolution_reason', 64)->nullable()->after('callback_error_reason');
        });

        // Guard: SQLite — bez MySQL MODIFY ENUM.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE temp_data MODIFY COLUMN status ENUM('pending', 'processed', 'late_success', 'late_manual_review', 'late_rejected', 'canceled', 'expired') DEFAULT 'pending'");
        }
        DB::table('temp_data')->where('status', 'late_success')->update(['status' => 'late_manual_review']);
    }

    public function down(): void
    {
        DB::table('temp_data')
            ->whereIn('status', ['late_manual_review', 'late_rejected'])
            ->update(['status' => 'late_success']);

        // Guard: SQLite — bez MySQL MODIFY ENUM.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE temp_data MODIFY COLUMN status ENUM('pending', 'processed', 'late_success', 'canceled', 'expired') DEFAULT 'pending'");
        }

        Schema::table('temp_data', function (Blueprint $table) {
            $table->dropColumn('resolution_reason');
        });
    }
};
