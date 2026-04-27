<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_advance_topups', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('agency_user_id');

            $table->string('merchant_transaction_id', 64)->unique();
            $table->decimal('amount', 10, 2);

            $table->string('status')->index();

            $table->json('bank_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index('agency_user_id');

            $table->foreign('agency_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_advance_topups');
    }
};

