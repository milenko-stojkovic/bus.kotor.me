<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Opciono: pošalji email korisniku kada plaćanje ne uspe (UX level-up).
 * Ako nema konfigurisanog Mailable, samo loguje.
 */
class NotifyUserPaymentFailed
{
    public function handle(PaymentFailed $event): void
    {
        $temp = $event->tempData;
        $email = $temp->email;
        if (empty($email)) {
            return;
        }

        // TODO: kreirati Mailable PaymentFailedMail sa porukom "Plaćanje je otkazano ili nije uspelo. Rezervacija nije sačuvana."
        // Za sada samo log (da ne puca ako Mailable ne postoji)
        Log::channel('payments')->info('Payment failed notification (email would be sent)', [
            'merchant_transaction_id' => $temp->merchant_transaction_id,
            'email' => $email,
        ]);
    }
}
