<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_advance_yearly_statements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('agency_user_id');
            $table->unsignedSmallInteger('year');

            $table->timestamp('sent_at')->nullable();
            $table->string('email')->nullable();

            $table->timestamps();

            $table->unique(['agency_user_id', 'year']);
            $table->index(['year', 'sent_at']);

            $table->foreign('agency_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_advance_yearly_statements');
    }
};

