<?php

namespace App\Console\Commands;

use App\Models\TempData;
use Illuminate\Console\Command;

/**
 * Cron (opciono): temp_data se ne briše – audit trail. Komanda ostaje za eventualno arhiviranje ili metrike.
 * V. docs/cron-commands.md. Frekvencija: daily().
 */
class CleanupOldTempData extends Command
{
    protected $signature = 'temp-data:cleanup';

    protected $description = 'Temp_data is retained for audit trail; no physical delete';

    public function handle(): int
    {
        $this->info('Temp_data rows are retained for audit trail. No delete performed.');
        return self::SUCCESS;
    }
}
