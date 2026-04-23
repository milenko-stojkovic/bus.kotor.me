<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_reservation_request_attachments', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('request_id');
            $table->string('original_name', 255);
            $table->string('stored_path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes');

            $table->timestamps();

            $table->foreign('request_id', 'fk_frratt_request')
                ->references('id')
                ->on('free_reservation_requests')
                ->onDelete('cascade');

            $table->index('request_id', 'idx_frratt_request');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_reservation_request_attachments');
    }
};

