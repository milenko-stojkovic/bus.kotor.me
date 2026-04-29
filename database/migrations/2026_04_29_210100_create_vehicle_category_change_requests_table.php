<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_category_change_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedInteger('old_vehicle_id')->index();
            $table->string('license_plate', 50)->index();
            $table->unsignedInteger('old_vehicle_type_id')->index();
            $table->unsignedInteger('requested_vehicle_type_id')->index();
            $table->string('status', 20)->default('pending')->index(); // pending|approved|rejected

            $table->string('document_original_name', 255)->nullable();
            $table->string('document_path', 500);
            $table->string('document_mime_type', 100);
            $table->unsignedBigInteger('document_size_bytes');

            $table->string('locale', 5);

            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->foreign('user_id', 'fk_vccr_user')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('old_vehicle_id', 'fk_vccr_old_vehicle')->references('id')->on('vehicles')->onDelete('restrict');
            $table->foreign('old_vehicle_type_id', 'fk_vccr_old_type')->references('id')->on('vehicle_types')->onDelete('restrict');
            $table->foreign('requested_vehicle_type_id', 'fk_vccr_req_type')->references('id')->on('vehicle_types')->onDelete('restrict');
            $table->foreign('reviewed_by_admin_id', 'fk_vccr_admin')->references('id')->on('admins')->nullOnDelete();

            // Avoid duplicate pending requests for same agency + plate + requested type.
            $table->unique(['user_id', 'license_plate', 'requested_vehicle_type_id', 'status'], 'uq_vccr_pending_dedupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_category_change_requests');
    }
};

