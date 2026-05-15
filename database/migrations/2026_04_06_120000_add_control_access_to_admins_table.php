<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('admins', 'control_access')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->boolean('control_access')->default(false)->after('password');
            });
        }

        if (Schema::hasColumn('admins', 'control_access')) {
            DB::table('admins')->where('username', 'control')->update(['control_access' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('admins', 'control_access')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('control_access');
            });
        }
    }
};
