<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->increments('id');

            $table->string('merchant_transaction_id', 64)->nullable();
            $table->unique('merchant_transaction_id', 'uq_res_merchant_tx');

            $table->unsignedInteger('drop_off_time_slot_id');
            $table->unsignedInteger('pick_up_time_slot_id');

            $table->date('reservation_date');

            $table->string('user_name');
            $table->string('country', 100);
            $table->string('license_plate', 50);

            $table->unsignedInteger('vehicle_type_id');

            $table->string('email');

            $table->string('fiscal_jir', 64)->nullable();
            $table->string('fiscal_ikof', 64)->nullable();
            $table->string('fiscal_qr', 255)->nullable();
            $table->string('fiscal_operator', 64)->nullable();
            $table->dateTime('fiscal_date')->nullable();

            $table->enum('status', ['paid', 'free'])->default('paid');

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->tinyInteger('email_sent')->default(0);

            // Foreign keys (names match dump)
            $table->foreign('drop_off_time_slot_id', 'fk_res_drop')->references('id')->on('list_of_time_slots');
            $table->foreign('pick_up_time_slot_id', 'fk_res_pick')->references('id')->on('list_of_time_slots');
            $table->foreign('vehicle_type_id', 'fk_res_vehicle')->references('id')->on('vehicle_types');

            // Indexes (uq_res_merchant_tx already indexes merchant_transaction_id)
            $table->index('reservation_date', 'idx_res_date');
            $table->index('status', 'idx_res_status');
            $table->index('vehicle_type_id', 'idx_res_vehicle');
            $table->index(['license_plate', 'reservation_date'], 'idx_res_plate_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};