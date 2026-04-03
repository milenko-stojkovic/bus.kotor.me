<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * invoice_sent_at + (legacy) invoice_pdf_path. PDF se danas ne čuva na disku (uklonjeno kasnijom migracijom); idempotentnost mejla preko invoice_sent_at.
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
