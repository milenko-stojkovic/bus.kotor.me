<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('limo_pickup_events', function (Blueprint $table) {
            $table->string('agency_country_snapshot')->nullable()->after('agency_email_snapshot');
            $table->string('service_name_snapshot')->nullable()->after('amount_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('limo_pickup_events', function (Blueprint $table) {
            $table->dropColumn(['agency_country_snapshot', 'service_name_snapshot']);
        });
    }
};
