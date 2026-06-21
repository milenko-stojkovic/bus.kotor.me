<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_category_change_request_attachments', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('size');
            $table->string('archive_provider', 32)->nullable()->after('archived_at');
            $table->string('archive_path', 500)->nullable()->after('archive_provider');
            $table->text('archive_error')->nullable()->after('archive_path');
            $table->timestamp('local_deleted_at')->nullable()->after('archive_error');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_category_change_request_attachments', function (Blueprint $table) {
            $table->dropColumn([
                'archived_at',
                'archive_provider',
                'archive_path',
                'archive_error',
                'local_deleted_at',
            ]);
        });
    }
};
