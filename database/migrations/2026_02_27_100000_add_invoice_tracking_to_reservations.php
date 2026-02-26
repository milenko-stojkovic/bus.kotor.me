<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotentni retry: "ako već postoji PDF – ne pravi novi", "ako je mail već poslat – ne šalji ponovo".
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('invoice_pdf_path', 255)->nullable()->after('fiscal_date');
            $table->timestamp('invoice_sent_at')->nullable()->after('invoice_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['invoice_pdf_path', 'invoice_sent_at']);
        });
    }
};
