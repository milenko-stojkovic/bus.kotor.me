<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_emails', function (Blueprint $table) {
            $table->string('purpose', 32)->default('reports')->after('email');
        });

        DB::table('report_emails')->update(['purpose' => 'reports']);

        Schema::table('report_emails', function (Blueprint $table) {
            $table->index('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('report_emails', function (Blueprint $table) {
            $table->dropIndex(['purpose']);
            $table->dropColumn('purpose');
        });
    }
};
