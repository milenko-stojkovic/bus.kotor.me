<?php

namespace App\Console\Commands;

use App\Models\TempData;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cleanup temp_data with retention for admin “Uvid”.
 * - Never delete active rows (status pending).
 * - Only delete rows older than configured retention days.
 * V. config/reservations.php and docs/cron-commands.md.
 */
class CleanupOldTempData extends Command
{
    protected $signature = 'temp-data:cleanup';

    protected $description = 'Cleanup old temp_data rows (retention-based) while preserving active/pending rows';

    public function handle(): int
    {
        $days = (int) config('reservations.temp_data_retention_days', 180);
        $days = max(1, $days);

        $cutoff = Carbon::now()->subDays($days);

        // Safety: do not delete pending (can still be relevant for payment/inquiry).
        $deleted = TempData::query()
            ->where('status', '!=', TempData::STATUS_PENDING)
            ->where('created_at', '<', $cutoff->toDateTimeString())
            ->delete();

        Log::channel('payments')->info('temp_data_cleanup_done', [
            'deleted' => $deleted,
            'retention_days' => $days,
            'cutoff' => $cutoff->toDateTimeString(),
        ]);

        $this->info("temp-data:cleanup deleted={$deleted} (retention_days={$days}, cutoff={$cutoff->toDateTimeString()})");
        return self::SUCCESS;
    }
}
