<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_report_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('period_type', 20); // daily|monthly|yearly
            $table->date('period_start');
            $table->date('period_end');
            $table->string('recipient_email', 255);
            $table->string('status', 20); // sending|sent|failed|skipped
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['period_type', 'period_start', 'period_end', 'recipient_email'],
                'uq_scheduled_report_delivery'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_report_deliveries');
    }
};

