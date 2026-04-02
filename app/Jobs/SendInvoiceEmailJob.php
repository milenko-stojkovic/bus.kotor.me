<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Support\UiText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        /** @var Reservation|null $reservation */
        $reservation = null;
        $claimed = false;

        DB::transaction(function () use (&$reservation, &$claimed): void {
            $reservation = Reservation::query()
                ->whereKey($this->reservationId)
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                return;
            }

            // Idempotent: ako je mail već poslat – ne šalji ponovo
            if ($reservation->invoice_sent_at !== null) {
                return;
            }

            // Prevent double-send from concurrent workers / retries in-flight.
            if ((int) $reservation->email_sent === 2) {
                return;
            }

            $reservation->update(['email_sent' => 2]); // sending
            $claimed = true;
        });

        if (! $reservation || ! $claimed) {
            return;
        }

        $email = $reservation->user_id
            ? ($reservation->user?->email ?? $reservation->email)
            : $reservation->email;
        if (empty($email)) {
            $reservation->update(['email_sent' => 0]);
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

        // Ensure PDF exists before sending email; otherwise users get an email without attachment.
        $fullPath = GenerateInvoicePdfJob::fullPathForReservation($reservation);
        if (! file_exists($fullPath)) {
            try {
                GenerateInvoicePdfJob::dispatchSync($reservation->id, $this->isFiscal);
                $reservation->refresh();
                $fullPath = GenerateInvoicePdfJob::fullPathForReservation($reservation);
            } catch (\Throwable $e) {
                Log::channel('single')->error('SendInvoiceEmailJob: PDF generation failed before email send', [
                    'reservation_id' => $reservation->id,
                    'is_fiscal' => $this->isFiscal,
                    'message' => $e->getMessage(),
                ]);
            }
        }
        if (! file_exists($fullPath)) {
            Log::channel('single')->warning('SendInvoiceEmailJob: PDF missing, skipping email send', [
                'reservation_id' => $reservation->id,
                'is_fiscal' => $this->isFiscal,
                'expected_path' => $fullPath,
            ]);
            $reservation->update(['email_sent' => 0]);
            app()->setLocale($previousLocale);
            return;
        }

        $subjectTemplate = UiText::t(
            'emails',
            'paid_invoice_email_subject',
            'Reservation confirmation #%1$d',
            $emailLocale
        );
        $subject = sprintf($subjectTemplate, $reservation->id);

        $body = $this->buildConfirmationText($reservation, $emailLocale);

        try {
            Mail::raw(
                $body,
                function ($message) use ($reservation, $email, $fromAddress, $fromName, $fullPath, $subject): void {
                    $message->to($email)
                        ->from($fromAddress, $fromName)
                        ->subject($subject);
                    $message->attach($fullPath, [
                        'as' => 'invoice-'.$reservation->id.'.pdf',
                        'mime' => 'application/pdf',
                    ]);
                }
            );
        } catch (\Throwable $e) {
            $reservation->update(['email_sent' => 0]);
            app()->setLocale($previousLocale);
            throw $e;
        }

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
