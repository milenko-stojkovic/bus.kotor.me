<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // company_name removed from users (legacy cleanup); keep migration for existing DBs.
            if (Schema::hasColumn('users', 'company_name')) {
                $table->dropColumn('company_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Intentionally no-op: we don't reintroduce legacy column.
        });
    }
};

