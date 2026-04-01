<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Support\UiText;
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

        $subjectTemplate = UiText::t(
            'emails',
            'paid_invoice_email_subject',
            'Reservation confirmation #%1$d',
            $emailLocale
        );
        $subject = sprintf($subjectTemplate, $reservation->id);

        $body = $this->buildConfirmationText($reservation, $emailLocale);

        Mail::raw(
            $body,
            function ($message) use ($reservation, $email, $fromAddress, $fromName, $fullPath, $subject): void {
                $message->to($email)
                    ->from($fromAddress, $fromName)
                    ->subject($subject);
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

    private function buildConfirmationText(Reservation $reservation, string $emailLocale): string
    {
        $jirSuffix = '';
        if ($reservation->fiscal_jir) {
            $jirSuffix = "\n\n".sprintf(
                UiText::t('emails', 'paid_invoice_email_jir_line', 'JIR: %1$s', $emailLocale),
                $reservation->fiscal_jir
            );
        }

        $bodyTemplate = UiText::t(
            'emails',
            'paid_invoice_email_body',
            "Hello,\n\nYour paid parking reservation #%1\$d is confirmed for date %2\$s.%3\$s\n\nA PDF copy of your invoice or confirmation is attached.\n\nThank you.",
            $emailLocale
        );

        return sprintf(
            $bodyTemplate,
            $reservation->id,
            $reservation->reservation_date->format('Y-m-d'),
            $jirSuffix
        );
    }
}
