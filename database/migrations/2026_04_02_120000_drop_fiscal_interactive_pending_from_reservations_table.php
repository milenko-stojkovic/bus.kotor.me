<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('reservations', 'fiscal_interactive_pending')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->dropColumn('fiscal_interactive_pending');
            });
        }
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->boolean('fiscal_interactive_pending')->default(false)->after('status');
        });
    }
};
