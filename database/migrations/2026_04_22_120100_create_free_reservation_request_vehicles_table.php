<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_reservation_request_vehicles', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('request_id');
            $table->string('license_plate', 50);
            $table->unsignedInteger('vehicle_type_id');

            $table->timestamps();

            $table->foreign('request_id', 'fk_frrv_request')->references('id')->on('free_reservation_requests')->onDelete('cascade');
            $table->foreign('vehicle_type_id', 'fk_frrv_vehicle_type')->references('id')->on('vehicle_types');

            $table->index('request_id', 'idx_frrv_request');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_reservation_request_vehicles');
    }
};

