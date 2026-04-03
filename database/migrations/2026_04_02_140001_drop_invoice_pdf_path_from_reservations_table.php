<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reservations', 'invoice_pdf_path')) {
            // Već uklonjeno ili nikad dodato (npr. sveža SQLite šema bez stare kolone).
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('invoice_pdf_path');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('reservations', 'invoice_pdf_path')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('invoice_pdf_path', 255)->nullable()->after('invoice_amount');
        });
    }
};
