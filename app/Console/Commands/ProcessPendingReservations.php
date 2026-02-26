<?php

namespace App\Console\Commands;

use App\Models\TempData;
use Illuminate\Console\Command;

/**
 * Cron: proverava temp_data sa statusom pending, pokušava naknadnu fiskalizaciju.
 * Uspeh → upis fiscal podataka u reservations, brisanje iz temp_data. Neuspeh → pending ili failed posle X pokušaja.
 * V. docs/cron-commands.md. Frekvencija: npr. everyFiveMinutes().
 */
class ProcessPendingReservations extends Command
{
    protected $signature = 'reservations:process-pending';

    protected $description = 'Process pending temp_data: attempt post-fiscalization, write to reservations and delete on success';

    public function handle(): int
    {
        $rows = TempData::where('status', TempData::STATUS_PENDING)->get();

        foreach ($rows as $temp) {
            // TODO: poziv fiskalnog API-ja; ako uspe:
            //   - kreiraj Reservation iz temp (user_id, snapshot, termini); eventualno PostFiscalizationData ako treba istorija
            //   - upiši fiscal_* u reservation
            //   - $temp->delete();
            // ako ne uspe: po broju pokušaja ostavi pending ili $temp->update(['status' => TempData::STATUS_CANCELED]);
        }

        $this->info('Processed '.$rows->count().' pending rows.');
        return self::SUCCESS;
    }
}
