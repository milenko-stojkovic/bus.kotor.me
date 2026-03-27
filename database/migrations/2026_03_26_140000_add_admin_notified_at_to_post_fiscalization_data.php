<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_fiscalization_data', function (Blueprint $table) {
            if (! Schema::hasColumn('post_fiscalization_data', 'admin_notified_at')) {
                $table->timestamp('admin_notified_at')->nullable()->after('resolved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('post_fiscalization_data', function (Blueprint $table) {
            if (Schema::hasColumn('post_fiscalization_data', 'admin_notified_at')) {
                $table->dropColumn('admin_notified_at');
            }
        });
    }
};

