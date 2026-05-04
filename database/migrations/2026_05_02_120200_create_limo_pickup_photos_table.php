<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('limo_pickup_photos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('limo_pickup_event_id')->index();
            $table->string('path');
            $table->enum('type', ['plate', 'context']);

            $table->timestamps();

            $table->foreign('limo_pickup_event_id', 'fk_limo_pickup_photos_event')
                ->references('id')
                ->on('limo_pickup_events')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('limo_pickup_photos');
    }
};
