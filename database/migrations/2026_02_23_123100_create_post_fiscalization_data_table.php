<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_fiscalization_data', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('reservation_id');
            $table->string('merchant_transaction_id', 64);

            $table->timestamps();

            $table->foreign('reservation_id', 'fk_post_fiscal_res')
                  ->references('id')
                  ->on('reservations');

            $table->index('merchant_transaction_id', 'idx_post_fiscal_tx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_fiscalization_data');
    }
};
