<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temp_data', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_transaction_id', 64)->unique();
            $table->unsignedBigInteger('drop_off_time_slot_id');
            $table->unsignedBigInteger('pick_up_time_slot_id');
            $table->date('reservation_date')->index();
            $table->string('user_name', 255);
            $table->string('country', 100);
            $table->string('license_plate', 50)->index();
            $table->unsignedBigInteger('vehicle_type_id')->index();
            $table->string('email', 255);
            $table->enum('status', ['pending', 'failed', 'late_success'])->default('pending')->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // foreign keys
            $table->foreign('drop_off_time_slot_id', 'fk_temp_drop')
                  ->references('id')->on('list_of_time_slots');
            $table->foreign('pick_up_time_slot_id', 'fk_temp_pick')
                  ->references('id')->on('list_of_time_slots');
            $table->foreign('vehicle_type_id')->references('id')->on('vehicle_types');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_data');
    }
};