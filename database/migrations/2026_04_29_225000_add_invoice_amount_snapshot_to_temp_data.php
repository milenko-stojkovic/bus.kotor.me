<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temp_data', function (Blueprint $table) {
            if (! Schema::hasColumn('temp_data', 'invoice_amount_snapshot')) {
                $table->decimal('invoice_amount_snapshot', 10, 2)->nullable()->after('vehicle_type_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('temp_data', function (Blueprint $table) {
            if (Schema::hasColumn('temp_data', 'invoice_amount_snapshot')) {
                $table->dropColumn('invoice_amount_snapshot');
            }
        });
    }
};

