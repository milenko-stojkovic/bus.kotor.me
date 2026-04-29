<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'status')) {
                $table->string('status', 20)->default('active')->after('vehicle_type_id')->index();
            }
        });

        // Allow storing both active and removed for same plate.
        Schema::table('vehicles', function (Blueprint $table) {
            // Old unique: uq_user_plate(user_id, license_plate)
            try {
                $table->dropUnique('uq_user_plate');
            } catch (\Throwable) {
                // ignore (already dropped / different driver)
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            // New unique: one row per status for a plate
            try {
                $table->unique(['user_id', 'license_plate', 'status'], 'uq_user_plate_status');
            } catch (\Throwable) {
                // ignore if already exists
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            try {
                $table->dropUnique('uq_user_plate_status');
            } catch (\Throwable) {
                //
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            try {
                $table->unique(['user_id', 'license_plate'], 'uq_user_plate');
            } catch (\Throwable) {
                //
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};

