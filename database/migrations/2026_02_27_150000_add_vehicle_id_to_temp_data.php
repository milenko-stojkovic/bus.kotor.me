<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('temp_data', 'vehicle_id')) {
            Schema::table('temp_data', function (Blueprint $table) {
                $table->unsignedInteger('vehicle_id')->nullable()->after('user_id');
            });
        }

        // Recover from partially-applied states: ensure exact type compatibility with vehicles.id (INT UNSIGNED).
        DB::statement('ALTER TABLE temp_data MODIFY COLUMN vehicle_id INT UNSIGNED NULL');

        $dbName = DB::getDatabaseName();
        $fkExists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', $dbName)
            ->where('TABLE_NAME', 'temp_data')
            ->where('CONSTRAINT_NAME', 'fk_temp_vehicle_id')
            ->exists();

        if (! $fkExists) {
            Schema::table('temp_data', function (Blueprint $table) {
                $table->foreign('vehicle_id', 'fk_temp_vehicle_id')
                    ->references('id')
                    ->on('vehicles')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $dbName = DB::getDatabaseName();
        $fkExists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', $dbName)
            ->where('TABLE_NAME', 'temp_data')
            ->where('CONSTRAINT_NAME', 'fk_temp_vehicle_id')
            ->exists();

        Schema::table('temp_data', function (Blueprint $table) use ($fkExists) {
            if ($fkExists) {
                $table->dropForeign('fk_temp_vehicle_id');
            }
            if (Schema::hasColumn('temp_data', 'vehicle_id')) {
                $table->dropColumn('vehicle_id');
            }
        });
    }
};
