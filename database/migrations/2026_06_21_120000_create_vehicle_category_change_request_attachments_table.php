<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_category_change_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_category_change_request_id')->index('vccra_request_id_idx');
            $table->string('disk', 32)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->foreign('vehicle_category_change_request_id', 'fk_vccra_request')
                ->references('id')
                ->on('vehicle_category_change_requests')
                ->cascadeOnDelete();
        });

        $now = now();
        $legacyRows = DB::table('vehicle_category_change_requests')
            ->whereNotNull('document_path')
            ->where('document_path', '!=', '')
            ->where('document_path', '!=', 'tmp')
            ->orderBy('id')
            ->get();

        foreach ($legacyRows as $row) {
            $exists = DB::table('vehicle_category_change_request_attachments')
                ->where('vehicle_category_change_request_id', $row->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('vehicle_category_change_request_attachments')->insert([
                'vehicle_category_change_request_id' => $row->id,
                'disk' => 'local',
                'path' => $row->document_path,
                'original_name' => $row->document_original_name ?: 'document',
                'mime_type' => $row->document_mime_type ?: 'application/octet-stream',
                'size' => (int) ($row->document_size_bytes ?? 0),
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_category_change_request_attachments');
    }
};
