<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_parking_data', function (Blueprint $table) {
            $table->increments('id');

            $table->date('date')->index();
            $table->unsignedInteger('time_slot_id');
            $table->foreign('time_slot_id', 'fk_daily_slot')
                ->references('id')->on('list_of_time_slots')
                ->cascadeOnDelete();

            $table->unsignedInteger('capacity');
            $table->unsignedInteger('reserved')->default(0);
            $table->unsignedInteger('pending')->default(0);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['date', 'time_slot_id'], 'unique_date_slot');
            $table->index('date', 'idx_date');
            $table->index('time_slot_id', 'idx_time_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_parking_data');
    }
};