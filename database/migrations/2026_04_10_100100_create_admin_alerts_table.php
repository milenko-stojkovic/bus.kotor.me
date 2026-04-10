<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('status', 32)->default('unread'); // unread | in_progress | done
            $table->string('title');
            $table->text('message');
            $table->json('payload_json')->nullable();
            $table->string('merchant_transaction_id', 64)->nullable()->index();
            $table->unsignedInteger('temp_data_id')->nullable()->index();
            $table->unsignedBigInteger('reservation_id')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('removed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_alerts');
    }
};
