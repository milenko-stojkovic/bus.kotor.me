<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('limo_qr_tokens', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('agency_user_id')->index();
            $table->string('token_hash')->unique();
            $table->date('valid_on')->index();

            $table->timestamps();

            $table->foreign('agency_user_id', 'fk_limo_qr_tokens_agency_user')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('limo_qr_tokens');
    }
};
