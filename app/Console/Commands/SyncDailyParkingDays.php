<?php

namespace App\Console\Commands;

use App\Models\SystemConfig;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncDailyParkingDays extends Command
{
    protected $signature = 'parking:sync-days {--days=90 : How many days ahead to ensure (inclusive of today)} {--delete-past=1 : Delete rows with date < today (1/0)}';

    protected $description = 'Ensure daily_parking_data has rows for today..N days ahead and optionally delete past days';

    public function handle(): int
    {
        $daysAhead = (int) $this->option('days');
        if ($daysAhead < 0) {
            $daysAhead = 0;
        }

        $deletePast = (string) $this->option('delete-past') !== '0';

        $today = Carbon::today();
        $start = $today->copy();
        $end = $today->copy()->addDays($daysAhead);

        $slotIds = DB::table('list_of_time_slots')->pluck('id')->map(fn ($v) => (int) $v)->all();
        if (empty($slotIds)) {
            $this->warn('No time slots found in list_of_time_slots.');
            return self::SUCCESS;
        }

        $capacityDefault = (int) (SystemConfig::getValue('available_parking_slots') ?? 9);
        if ($capacityDefault < 0) {
            $capacityDefault = 0;
        }

        if ($deletePast) {
            $deleted = DB::table('daily_parking_data')
                ->whereDate('date', '<', $today->toDateString())
                ->delete();
            $this->info('Deleted past daily_parking_data rows: '.$deleted);
        }

        $rows = [];
        $now = now();
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            foreach ($slotIds as $slotId) {
                $rows[] = [
                    'date' => $date,
                    'time_slot_id' => $slotId,
                    'capacity' => in_array($slotId, [1, 41], true) ? 999 : $capacityDefault,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $insertedOrUpdated = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            // Upsert only capacity and updated_at; do NOT touch reserved/pending.
            DB::table('daily_parking_data')->upsert(
                $chunk,
                ['date', 'time_slot_id'],
                ['capacity', 'updated_at']
            );
            $insertedOrUpdated += count($chunk);
        }

        $this->info('Synced daily_parking_data rows (upsert attempts): '.$insertedOrUpdated);
        $this->info('Range ensured: '.$start->toDateString().' .. '.$end->toDateString());

        return self::SUCCESS;
    }
}

