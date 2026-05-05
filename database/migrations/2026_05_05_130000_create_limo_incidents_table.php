<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('limo_incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('incident_uuid')->unique();
            $table->enum('type', [
                'qr_insufficient_funds',
                'plate_insufficient_funds',
                'unregistered_vehicle_with_branding',
                'invalid_qr_token',
                'driver_non_cooperative',
            ]);
            $table->string('license_plate_snapshot')->nullable();
            $table->foreignId('agency_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('agency_name_snapshot')->nullable();
            $table->string('agency_email_snapshot')->nullable();
            $table->string('visible_agency_name')->nullable();
            $table->string('plate_photo_path');
            $table->string('branding_photo_path')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->foreignId('recorded_by_limo_admin_id')->constrained('admins')->restrictOnDelete();
            $table->text('device_info')->nullable();
            $table->timestamp('communal_email_sent_at')->nullable();
            $table->foreignId('admin_alert_id')->nullable()->constrained('admin_alerts')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('limo_incidents');
    }
};
