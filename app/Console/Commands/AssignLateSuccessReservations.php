<?php

namespace App\Console\Commands;

use App\Models\TempData;
use Illuminate\Console\Command;

/**
 * Cron: temp_data late_success → admin može ažurirati datum/termin; kreiranje rezervacije iz snapshota, brisanje sloga.
 * V. docs/cron-commands.md. Frekvencija: po potrebi (ručno ili everyFiveMinutes / everyFifteenMinutes).
 */
class AssignLateSuccessReservations extends Command
{
    protected $signature = 'reservations:assign-late-success';

    protected $description = 'Process late_success temp_data: create reservation from snapshot (after admin updates if any) and delete temp row';

    public function handle(): int
    {
        $rows = TempData::where('status', TempData::STATUS_LATE_SUCCESS)->get();

        foreach ($rows as $temp) {
            // TODO: ako admin već ažurirao datum/drop_off/pick_up u temp_data, koristi te vrednosti
            // Kreiraj Reservation::create([...]) iz $temp (user_id, snapshot, termini); zatim $temp->delete();
        }

        $this->info('Processed '.$rows->count().' late_success rows.');
        return self::SUCCESS;
    }
}
