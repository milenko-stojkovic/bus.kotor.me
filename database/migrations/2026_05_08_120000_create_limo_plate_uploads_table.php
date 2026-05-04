<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('limo_plate_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('upload_token', 64)->unique();
            $table->string('path');
            $table->text('ocr_text')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->text('device_info')->nullable();
            $table->foreignId('uploaded_by_limo_admin_id')->constrained('admins')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('limo_plate_uploads');
    }
};
