<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_type_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('vehicle_type_id');
            $table->foreign('vehicle_type_id')->references('id')->on('vehicle_types')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['vehicle_type_id', 'locale'], 'uniq_vehicle_type_locale');
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_type_translations');
    }
};