<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('limo_plate_uploads', function (Blueprint $table) {
            $table->unsignedSmallInteger('plate_crop_left_bp')->nullable()->after('path');
            $table->unsignedSmallInteger('plate_crop_top_bp')->nullable()->after('plate_crop_left_bp');
            $table->unsignedSmallInteger('plate_crop_width_bp')->nullable()->after('plate_crop_top_bp');
            $table->unsignedSmallInteger('plate_crop_height_bp')->nullable()->after('plate_crop_width_bp');
        });
    }

    public function down(): void
    {
        Schema::table('limo_plate_uploads', function (Blueprint $table) {
            $table->dropColumn([
                'plate_crop_left_bp',
                'plate_crop_top_bp',
                'plate_crop_width_bp',
                'plate_crop_height_bp',
            ]);
        });
    }
};
