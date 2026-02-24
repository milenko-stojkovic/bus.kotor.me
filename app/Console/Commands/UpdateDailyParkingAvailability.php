<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Cron: ažurira daily_parking_data – povećava reserved, smanjuje pending kada se rezervacija fiskalizuje.
 * V. docs/cron-commands.md. Frekvencija: everyFiveMinutes() / everyTenMinutes().
 */
class UpdateDailyParkingAvailability extends Command
{
    protected $signature = 'parking:update-availability';

    protected $description = 'Update daily_parking_data reserved/pending from reservations and temp_data';

    public function handle(): int
    {
        // TODO: za svaku rezervaciju (po reservation_date, drop_off_time_slot_id) povećaj reserved u daily_parking_data
        // Za temp_data pending povećaj pending; kad rezervacija postane fiskalizovana, smanji pending i povećaj reserved
        // Opciono: reset kapaciteta za novi datum ako treba
        $this->info('Parking availability update run.');
        return self::SUCCESS;
    }
}
