<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->decimal('price', 10, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};
