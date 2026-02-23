<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_of_time_slots', function (Blueprint $table) {
            $table->id();
            $table->string('time_slot', 20);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_of_time_slots');
    }
};
