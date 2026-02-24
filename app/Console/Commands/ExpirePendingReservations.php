<?php

namespace App\Console\Commands;

use App\Models\DailyParkingData;
use App\Models\TempData;
use Illuminate\Console\Command;

/**
 * Cron: temp_data pending duže od X min (npr. 30) → status failed. Nikad ne brisati (audit trail).
 * Oslobodi soft-lock (decrement daily_parking_data.pending). V. docs/cron-commands.md.
 */
class ExpirePendingReservations extends Command
{
    protected $signature = 'reservations:expire-pending';

    protected $description = 'Expire temp_data pending older than threshold: mark failed, release soft-lock';

    public function handle(): int
    {
        $minutes = (int) config('reservations.pending_expire_minutes');
        $cutoff = now()->subMinutes($minutes);

        $rows = TempData::where('status', TempData::STATUS_PENDING)
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($rows as $temp) {
            $temp->update(['status' => TempData::STATUS_FAILED]);
            $daily = DailyParkingData::where('date', $temp->reservation_date)
                ->where('time_slot_id', $temp->drop_off_time_slot_id)
                ->first();
            if ($daily) {
                $daily->decrement('pending');
            }
        }

        $this->info('Expired '.$rows->count().' pending rows.');
        return self::SUCCESS;
    }
}
