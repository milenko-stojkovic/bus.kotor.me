<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_file_archives', function (Blueprint $table) {
            $table->timestamp('preview_restored_at')->nullable()->after('local_deleted_at');
            $table->timestamp('preview_expires_at')->nullable()->after('preview_restored_at');
        });
    }

    public function down(): void
    {
        Schema::table('external_file_archives', function (Blueprint $table) {
            $table->dropColumn(['preview_restored_at', 'preview_expires_at']);
        });
    }
};
