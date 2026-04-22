<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_reservation_requests', function (Blueprint $table) {
            $table->increments('id');

            $table->string('locale', 10);

            $table->string('institution_name');
            $table->string('institution_email');
            $table->string('institution_phone', 32);

            $table->date('reservation_date');
            $table->unsignedInteger('drop_off_time_slot_id');
            $table->unsignedInteger('pick_up_time_slot_id');

            $table->string('country', 100);

            $table->enum('status', ['submitted', 'updated', 'fulfilled', 'rejected'])->default('submitted');

            $table->timestamps();

            $table->foreign('drop_off_time_slot_id', 'fk_frr_drop')->references('id')->on('list_of_time_slots');
            $table->foreign('pick_up_time_slot_id', 'fk_frr_pick')->references('id')->on('list_of_time_slots');

            $table->index('reservation_date', 'idx_frr_date');
            $table->index('status', 'idx_frr_status');
            $table->index(['institution_email', 'reservation_date'], 'idx_frr_email_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_reservation_requests');
    }
};

