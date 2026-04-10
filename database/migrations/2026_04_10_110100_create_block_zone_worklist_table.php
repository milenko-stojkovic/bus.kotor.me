<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_zone_worklist', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_transaction_id', 64)->index();
            $table->string('status', 32); // pending_payment | ready_to_adjust

            $table->date('old_date')->index();
            $table->unsignedInteger('old_drop_off');
            $table->unsignedInteger('old_pick_up');
            $table->boolean('affected_drop_off')->default(false);
            $table->boolean('affected_pick_up')->default(false);

            $table->json('snapshot_json');

            // Optional helpers (simplify joins; still one row per merchant_transaction_id)
            $table->unsignedBigInteger('reservation_id')->nullable()->index();
            $table->unsignedInteger('temp_data_id')->nullable()->index();

            $table->timestamps();

            $table->unique(['merchant_transaction_id'], 'uniq_block_zone_mtid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_zone_worklist');
    }
};

