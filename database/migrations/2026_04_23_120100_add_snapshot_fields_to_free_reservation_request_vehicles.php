<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('free_reservation_request_vehicles', function (Blueprint $table) {
            $table->unsignedBigInteger('agency_vehicle_id')->nullable()->after('request_id');
            $table->string('vehicle_type_label', 255)->nullable()->after('vehicle_type_id');

            $table->index(['request_id', 'agency_vehicle_id'], 'idx_frrv_request_agency_vehicle');
        });
    }

    public function down(): void
    {
        Schema::table('free_reservation_request_vehicles', function (Blueprint $table) {
            $table->dropIndex('idx_frrv_request_agency_vehicle');
            $table->dropColumn('agency_vehicle_id');
            $table->dropColumn('vehicle_type_label');
        });
    }
};

