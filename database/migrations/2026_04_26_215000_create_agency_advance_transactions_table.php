<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_advance_transactions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('agency_user_id');
            $table->decimal('amount', 10, 2);
            $table->string('type');

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->string('merchant_transaction_id', 64)->nullable();

            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by_admin_id')->nullable();

            $table->timestamps();

            $table->index('agency_user_id');
            $table->index('merchant_transaction_id');
            $table->index('type');
            $table->index(['reference_type', 'reference_id']);

            $table->foreign('agency_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Reference admins only if the table exists in this app/database.
            if (Schema::hasTable('admins')) {
                $table->foreign('created_by_admin_id')
                    ->references('id')
                    ->on('admins')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_advance_transactions');
    }
};

