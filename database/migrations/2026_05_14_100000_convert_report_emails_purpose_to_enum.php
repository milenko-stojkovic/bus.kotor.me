<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('report_emails')->where('purpose', 'reports')->update(['purpose' => 'report']);

        Schema::table('report_emails', function (Blueprint $table) {
            $table->dropIndex(['purpose']);
        });

        Schema::table('report_emails', function (Blueprint $table) {
            $table->enum('purpose', ['report', 'limo_incidents'])->default('report')->change();
        });

        Schema::table('report_emails', function (Blueprint $table) {
            $table->index('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('report_emails', function (Blueprint $table) {
            $table->dropIndex(['purpose']);
        });

        Schema::table('report_emails', function (Blueprint $table) {
            $table->string('purpose', 32)->default('reports')->change();
        });

        DB::table('report_emails')->where('purpose', 'report')->update(['purpose' => 'reports']);

        Schema::table('report_emails', function (Blueprint $table) {
            $table->index('purpose');
        });
    }
};
