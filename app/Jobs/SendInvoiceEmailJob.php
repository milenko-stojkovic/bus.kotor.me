<?php

namespace App\Jobs;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Sends invoice/confirmation email to customer. Fiscal or non-fiscal PDF attached.
 * Idempotent: ako je mail već poslat (invoice_sent_at set) – ne šalji ponovo (retry safe).
 * Mail must be sent from bus@kotor.me (MAIL_FROM_ADDRESS).
 */
class SendInvoiceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $reservationId,
        public bool $isFiscal = true
    ) {}

    public function handle(): void
    {
        $reservation = Reservation::find($this->reservationId);
        if (! $reservation) {
            return;
        }

        // Idempotent: ako je mail već poslat – ne šalji ponovo
        if ($reservation->invoice_sent_at !== null) {
            return;
        }

        $email = $reservation->user_id
            ? ($reservation->user?->email ?? $reservation->email)
            : $reservation->email;
        if (empty($email)) {
            return;
        }

        // Email language: auth -> user.lang; guest -> reservation.preferred_locale (set at checkout from browser/session)
        $emailLocale = $reservation->user_id
            ? ($reservation->user?->lang ?? 'en')
            : ($reservation->preferred_locale ?? 'en');
        if (! in_array($emailLocale, ['en', 'cg'], true)) {
            $emailLocale = 'en';
        }
        $previousLocale = app()->getLocale();
        app()->setLocale($emailLocale);

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        $fullPath = GenerateInvoicePdfJob::fullPathForReservation($reservation);

        Mail::raw(
            $this->buildConfirmationText($reservation),
            function ($message) use ($reservation, $email, $fromAddress, $fromName, $fullPath): void {
                $message->to($email)
                    ->from($fromAddress, $fromName)
                    ->subject(__('Reservation confirmation').' #'.$reservation->id);
                if (file_exists($fullPath)) {
                    $message->attach($fullPath, [
                        'as' => 'invoice-'.$reservation->id.'.pdf',
                        'mime' => 'application/pdf',
                    ]);
                }
            }
        );

        app()->setLocale($previousLocale);

        $reservation->markConfirmationEmailSent();
    }

    private function buildConfirmationText(Reservation $reservation): string
    {
        $text = __('Reservation confirmed.')."\n"
            .__('Reservation ID').': '.$reservation->id."\n"
            .__('Date').': '.$reservation->reservation_date->format('Y-m-d')."\n";
        if ($reservation->fiscal_jir) {
            $text .= __('JIR').': '.$reservation->fiscal_jir."\n";
        }
        $text .= "\n".__('Invoice is attached.');

        return $text;
    }
}
