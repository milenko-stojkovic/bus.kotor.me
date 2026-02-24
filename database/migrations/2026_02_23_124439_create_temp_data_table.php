<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temp_data', function (Blueprint $table) {
            $table->increments('id');
            $table->string('merchant_transaction_id', 64)->unique();
            $table->unsignedInteger('drop_off_time_slot_id');
            $table->unsignedInteger('pick_up_time_slot_id');
            $table->date('reservation_date');
            $table->string('user_name', 255);
            $table->string('country', 100);
            $table->string('license_plate', 50);
            $table->unsignedInteger('vehicle_type_id');
            $table->string('email', 255);
            $table->enum('status', ['pending', 'failed', 'late_success'])->default('pending');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // foreign keys
            $table->foreign('drop_off_time_slot_id', 'fk_temp_drop')
                  ->references('id')->on('list_of_time_slots');
            $table->foreign('pick_up_time_slot_id', 'fk_temp_pick')
                  ->references('id')->on('list_of_time_slots');
            $table->foreign('vehicle_type_id', 'fk_temp_vehicle')->references('id')->on('vehicle_types');

            $table->index('reservation_date', 'idx_temp_date');
            $table->index('status', 'idx_temp_status');
            $table->index('vehicle_type_id', 'idx_temp_vehicle');
            $table->index('merchant_transaction_id', 'idx_temp_merchant_tx');
            $table->index(['license_plate', 'reservation_date'], 'idx_temp_plate_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_data');
    }
};