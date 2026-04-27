<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('free_reservation_request_vehicles', function (Blueprint $table) {
            $table->unsignedInteger('segment_id')->nullable()->after('request_id');

            $table->foreign('segment_id', 'fk_frrv_segment')
                ->references('id')
                ->on('free_reservation_request_segments')
                ->onDelete('cascade');

            $table->index('segment_id', 'idx_frrv_segment');
        });

        // Backfill segment_id for existing rows (all vehicles belong to the single migrated segment).
        $segments = DB::table('free_reservation_request_segments')
            ->select(['id', 'request_id'])
            ->where('position', 1)
            ->orderBy('id')
            ->get();

        foreach ($segments as $seg) {
            DB::table('free_reservation_request_vehicles')
                ->where('request_id', $seg->request_id)
                ->whereNull('segment_id')
                ->update(['segment_id' => $seg->id]);
        }
    }

    public function down(): void
    {
        Schema::table('free_reservation_request_vehicles', function (Blueprint $table) {
            $table->dropForeign('fk_frrv_segment');
            $table->dropIndex('idx_frrv_segment');
            $table->dropColumn('segment_id');
        });
    }
};

