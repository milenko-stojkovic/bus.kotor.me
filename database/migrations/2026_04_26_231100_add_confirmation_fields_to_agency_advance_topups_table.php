<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_advance_topups', function (Blueprint $table) {
            $table->timestamp('confirmation_sent_at')->nullable()->after('failed_at');
            $table->string('confirmation_email')->nullable()->after('confirmation_sent_at');
            // Claim token for idempotent sending (do NOT treat as "sent").
            $table->timestamp('confirmation_sending_at')->nullable()->after('confirmation_email');
        });
    }

    public function down(): void
    {
        Schema::table('agency_advance_topups', function (Blueprint $table) {
            $table->dropColumn(['confirmation_sent_at', 'confirmation_email', 'confirmation_sending_at']);
        });
    }
};

