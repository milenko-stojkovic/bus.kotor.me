<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Services\Pdf\FreeReservationPdfGenerator;
use App\Support\UiText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Besplatna rezervacija: potvrda na email sa PDF prilogom.
 * PDF iz baze (renderBinary); na grešku: email_sent → NOT_SENT, job fail (queue retry); bez fallback regeneracije.
 * Idempotentno: invoice_sent_at + lock na EMAIL_SENDING (isto kao plaćeni job).
 */
class SendFreeReservationConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 45;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function __construct(
        public int $reservationId
    ) {}

    public function failed(?Throwable $e): void
    {
        Reservation::query()->whereKey($this->reservationId)->update([
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);
        $mtid = Reservation::query()->whereKey($this->reservationId)->value('merchant_transaction_id');
        Log::channel('payments')->error('free_reservation_email_job_exhausted', [
            'reservation_id' => $this->reservationId,
            'merchant_transaction_id' => $mtid,
            'message' => $e?->getMessage(),
            'exception' => $e !== null ? $e::class : null,
        ]);
    }

    public function handle(FreeReservationPdfGenerator $pdfGenerator): void
    {
        /** @var Reservation|null $reservation */
        $reservation = null;
        $claimed = false;

        DB::transaction(function () use (&$reservation, &$claimed): void {
            $reservation = Reservation::query()
                ->whereKey($this->reservationId)
                ->lockForUpdate()
                ->first();

            if (! $reservation || $reservation->status !== 'free') {
                return;
            }

            if ($reservation->invoice_sent_at !== null) {
                return;
            }

            if ((int) $reservation->email_sent === Reservation::EMAIL_SENDING) {
                return;
            }

            $reservation->update(['email_sent' => Reservation::EMAIL_SENDING]);
            $claimed = true;
        });

        if (! $reservation || ! $claimed) {
            return;
        }

        $email = $reservation->user_id
            ? ($reservation->user?->email ?? $reservation->email)
            : $reservation->email;
        if ($email === '' || $email === null) {
            $reservation->update(['email_sent' => Reservation::EMAIL_NOT_SENT]);

            return;
        }

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

        $subjectTemplate = UiText::t(
            'emails',
            'free_reservation_subject',
            'Free reservation confirmed #%1$d',
            $emailLocale
        );
        $subject = sprintf($subjectTemplate, $reservation->id);

        $bodyTemplate = UiText::t(
            'emails',
            'free_reservation_body',
            "Hello,\n\nYour free parking reservation #%1\$d is confirmed for %2\$s.\nNo payment was required — this email is not a fiscal invoice.\n\nThank you.",
            $emailLocale
        );
        $body = sprintf(
            $bodyTemplate,
            $reservation->id,
            $reservation->reservation_date->format('Y-m-d')
        );

        $tmpPath = null;
        try {
            $pdfBinary = $pdfGenerator->renderBinary($reservation);
            if ($pdfBinary === '') {
                throw new RuntimeException('Free reservation PDF empty after renderBinary.');
            }

            $tmpPath = tempnam(sys_get_temp_dir(), 'bus_free_');
            if ($tmpPath === false) {
                throw new RuntimeException('tempnam failed for free reservation PDF attachment.');
            }

            file_put_contents($tmpPath, $pdfBinary);
            Mail::raw($body, function ($message) use ($email, $fromAddress, $fromName, $subject, $reservation, $tmpPath): void {
                $message->to($email)
                    ->from($fromAddress, $fromName)
                    ->subject($subject);
                $message->attach($tmpPath, [
                    'as' => 'potvrda-besplatna-rezervacija-'.$reservation->id.'.pdf',
                    'mime' => 'application/pdf',
                ]);
            });

            $reservation->markConfirmationEmailSent();
            Log::channel('payments')->info('free_reservation_email_sent', [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'user_id' => $reservation->user_id,
            ]);
        } catch (Throwable $e) {
            Log::channel('payments')->warning('free_reservation_email_send_failed', [
                'reservation_id' => $reservation->id,
                'merchant_transaction_id' => $reservation->merchant_transaction_id,
                'user_id' => $reservation->user_id,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
            Log::channel('single')->error('SendFreeReservationConfirmationJob failed', [
                'reservation_id' => $reservation->id,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
            $reservation->update(['email_sent' => Reservation::EMAIL_NOT_SENT]);
            throw $e;
        } finally {
            if (is_string($tmpPath)) {
                @unlink($tmpPath);
            }
            app()->setLocale($previousLocale);
        }
    }
}
