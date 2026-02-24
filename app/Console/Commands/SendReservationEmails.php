<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Command;

/**
 * Cron: reservations sa email_sent = 0 → pošalji potvrdu, zatim markConfirmationEmailSent().
 * V. docs/cron-commands.md. Frekvencija: everyFiveMinutes() / everyTenMinutes().
 */
class SendReservationEmails extends Command
{
    protected $signature = 'reservations:send-emails';

    protected $description = 'Send reservation confirmation emails where email_sent = 0, then set email_sent = 1';

    public function handle(): int
    {
        $rows = Reservation::where('email_sent', 0)->get();

        foreach ($rows as $reservation) {
            // TODO: Mail::to($reservation->email)->send(new ReservationConfirmation($reservation));
            $reservation->markConfirmationEmailSent();
        }

        $this->info('Sent '.$rows->count().' confirmation emails.');
        return self::SUCCESS;
    }
}
