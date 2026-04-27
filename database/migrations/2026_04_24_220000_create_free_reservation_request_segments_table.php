<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_reservation_request_segments', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('request_id');
            $table->date('reservation_date');
            $table->unsignedInteger('drop_off_time_slot_id');
            $table->unsignedInteger('pick_up_time_slot_id');
            $table->unsignedInteger('position')->default(1);

            $table->timestamps();

            $table->foreign('request_id', 'fk_frrs_request')
                ->references('id')
                ->on('free_reservation_requests')
                ->onDelete('cascade');

            $table->foreign('drop_off_time_slot_id', 'fk_frrs_drop')
                ->references('id')
                ->on('list_of_time_slots');

            $table->foreign('pick_up_time_slot_id', 'fk_frrs_pick')
                ->references('id')
                ->on('list_of_time_slots');

            $table->index(['request_id', 'position'], 'idx_frrs_request_position');
            $table->index('reservation_date', 'idx_frrs_date');
        });

        // Migrate existing one-segment model into segments (one segment per request).
        // We keep legacy columns on free_reservation_requests for backwards compatibility,
        // but segments are the new source-of-truth for drop/pick grouping.
        $now = now();
        $requests = DB::table('free_reservation_requests')->select([
            'id',
            'reservation_date',
            'drop_off_time_slot_id',
            'pick_up_time_slot_id',
        ])->orderBy('id')->get();

        foreach ($requests as $r) {
            DB::table('free_reservation_request_segments')->insert([
                'request_id' => $r->id,
                'reservation_date' => $r->reservation_date,
                'drop_off_time_slot_id' => $r->drop_off_time_slot_id,
                'pick_up_time_slot_id' => $r->pick_up_time_slot_id,
                'position' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('free_reservation_request_segments');
    }
};

