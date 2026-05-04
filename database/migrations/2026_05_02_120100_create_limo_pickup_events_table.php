<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('limo_pickup_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('agency_user_id')->nullable()->index();
            $table->enum('source', ['qr', 'plate', 'incident']);
            $table->string('qr_token_hash')->nullable()->index();
            $table->date('qr_valid_on')->nullable()->index();
            $table->unsignedInteger('vehicle_id')->nullable()->index();
            $table->string('license_plate_snapshot')->nullable();
            $table->decimal('amount_snapshot', 10, 2);
            $table->timestamp('occurred_at')->index();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->unsignedBigInteger('recorded_by_limo_admin_id')->index();
            $table->text('device_info')->nullable();
            $table->enum('status', ['pending_fiscal', 'fiscalized', 'fiscal_failed', 'incident']);
            $table->string('fiscal_jir')->nullable();
            $table->string('fiscal_ikof')->nullable();
            $table->text('fiscal_qr')->nullable();
            $table->string('fiscal_operator')->nullable();
            $table->timestamp('fiscal_date')->nullable();

            $table->timestamps();

            $table->foreign('agency_user_id', 'fk_limo_pickup_agency_user')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('vehicle_id', 'fk_limo_pickup_vehicle')
                ->references('id')
                ->on('vehicles')
                ->nullOnDelete();

            $table->foreign('recorded_by_limo_admin_id', 'fk_limo_pickup_recorded_by')
                ->references('id')
                ->on('admins')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('limo_pickup_events');
    }
};
