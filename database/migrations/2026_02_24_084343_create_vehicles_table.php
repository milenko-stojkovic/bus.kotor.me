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
        if (Schema::hasTable('vehicles')) {
            return;
        }

        Schema::create('vehicles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('license_plate', 50);
            $table->unsignedInteger('vehicle_type_id')->index();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_id', 'license_plate'], 'uq_user_plate');
            $table->foreign('user_id', 'fk_vehicles_user')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
            $table->foreign('vehicle_type_id', 'fk_vehicles_type')
                  ->references('id')->on('vehicle_types')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
