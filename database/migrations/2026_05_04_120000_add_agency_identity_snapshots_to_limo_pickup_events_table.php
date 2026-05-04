<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('limo_pickup_events', function (Blueprint $table) {
            $table->string('agency_name_snapshot')->nullable()->after('agency_user_id');
            $table->string('agency_email_snapshot')->nullable()->after('agency_name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('limo_pickup_events', function (Blueprint $table) {
            $table->dropColumn(['agency_name_snapshot', 'agency_email_snapshot']);
        });
    }
};
