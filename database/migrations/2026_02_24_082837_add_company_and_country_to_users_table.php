<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'company_name')) {
                $table->string('company_name', 255)->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'country')) {
                $table->string('country', 100)->nullable()->after('company_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('users', 'company_name')) {
                $columns[] = 'company_name';
            }
            if (Schema::hasColumn('users', 'country')) {
                $columns[] = 'country';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
