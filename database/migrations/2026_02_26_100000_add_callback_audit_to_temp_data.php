<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail za callback: raw payload, error code/reason; status 'processed' (nikad ne brisati temp_data).
     */
    public function up(): void
    {
        Schema::table('temp_data', function (Blueprint $table) {
            $table->json('raw_callback_payload')->nullable()->after('status');
            $table->string('callback_error_code', 64)->nullable()->after('raw_callback_payload');
            $table->text('callback_error_reason')->nullable()->after('callback_error_code');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE temp_data MODIFY COLUMN status ENUM('pending', 'failed', 'late_success', 'processed') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        DB::table('temp_data')->where('status', 'processed')->update(['status' => 'failed']);
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE temp_data MODIFY COLUMN status ENUM('pending', 'failed', 'late_success') DEFAULT 'pending'");
        }
        Schema::table('temp_data', function (Blueprint $table) {
            $table->dropColumn(['raw_callback_payload', 'callback_error_code', 'callback_error_reason']);
        });
    }
};
