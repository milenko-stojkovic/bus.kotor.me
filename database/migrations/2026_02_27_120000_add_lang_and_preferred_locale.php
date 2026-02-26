<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UI/email language: users.lang (auth), preferred_locale (guest at checkout).
     * Invoice PDF is always Montenegrin (cg) – legal requirement.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('lang', 5)->default('en')->after('email');
        });

        Schema::table('temp_data', function (Blueprint $table) {
            $table->string('preferred_locale', 5)->nullable()->after('email');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('preferred_locale', 5)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('lang');
        });
        Schema::table('temp_data', function (Blueprint $table) {
            $table->dropColumn('preferred_locale');
        });
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('preferred_locale');
        });
    }
};
