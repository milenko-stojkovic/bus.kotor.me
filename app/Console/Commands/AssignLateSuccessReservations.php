<?php

namespace App\Console\Commands;

use App\Models\TempData;
use Illuminate\Console\Command;

/**
 * Cron: temp_data late_success → (V1: stub) redovi ostaju za admin pregled.
 *
 * Namjerno u V1: ne kreira se rezervacija automatski; late_success služi kao „incident“ za
 * manual review. Kad se implementira: kreirati rezervaciju iz snapshota ili označiti za admin.
 * V. docs/payment-v1-production-audit.md – sekcija „Namjerna odstupanja“.
 */
class AssignLateSuccessReservations extends Command
{
    protected $signature = 'reservations:assign-late-success';

    protected $description = 'Process late_success temp_data (V1: stub – rows kept for admin review)';

    public function handle(): int
    {
        $rows = TempData::where('status', TempData::STATUS_LATE_SUCCESS)->get();

        foreach ($rows as $temp) {
            // V1: ništa; red ostaje za admin. Kasnije: Reservation::create iz $temp ili incident flow.
        }

        $this->info('Processed '.$rows->count().' late_success rows (V1 stub – no reservation created).');

        return self::SUCCESS;
    }
}
