<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_file_archives', function (Blueprint $table) {
            $table->boolean('archived_derivative')->default(false)->after('original_local_path');
            $table->string('derivative_source_path', 500)->nullable()->after('archived_derivative');
            $table->json('derivative_options')->nullable()->after('derivative_source_path');
        });
    }

    public function down(): void
    {
        Schema::table('external_file_archives', function (Blueprint $table) {
            $table->dropColumn(['archived_derivative', 'derivative_source_path', 'derivative_options']);
        });
    }
};
