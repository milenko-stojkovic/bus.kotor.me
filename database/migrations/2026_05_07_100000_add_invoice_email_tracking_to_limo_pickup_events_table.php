<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('limo_pickup_events', function (Blueprint $table) {
            $table->unsignedTinyInteger('email_sent')->default(0)->after('fiscal_date');
            $table->timestamp('invoice_email_sent_at')->nullable()->after('email_sent');
        });
    }

    public function down(): void
    {
        Schema::table('limo_pickup_events', function (Blueprint $table) {
            $table->dropColumn(['email_sent', 'invoice_email_sent_at']);
        });
    }
};
