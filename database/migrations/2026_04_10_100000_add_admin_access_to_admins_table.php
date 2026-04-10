<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->boolean('admin_access')->default(false)->after('control_access');
        });

        // Glavni admin panel (`/admin`): admin | Control dolasci (`/control`): control — međusobno isključivo.
        DB::table('admins')->where('username', 'admin')->update([
            'admin_access' => true,
            'control_access' => false,
        ]);
        DB::table('admins')->where('username', 'control')->update([
            'admin_access' => false,
            'control_access' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('admin_access');
        });
    }
};
