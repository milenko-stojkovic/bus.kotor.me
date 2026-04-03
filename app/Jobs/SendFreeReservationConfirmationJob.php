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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Besplatna rezervacija: potvrda na email sa PDF prilogom (potvrda besplatne rezervacije, cg).
 * PDF se generiše u privremenom fajlu pri slanju; ne čuva se u storage/app.
 * Idempotentno (invoice_sent_at).
 */
class SendFreeReservationConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $reservationId
    ) {}

    public function handle(FreeReservationPdfGenerator $pdfGenerator): void
    {
        $reservation = Reservation::find($this->reservationId);
        if (! $reservation || $reservation->status !== 'free') {
            return;
        }

        if ($reservation->invoice_sent_at !== null) {
            return;
        }

        $email = $reservation->user_id
            ? ($reservation->user?->email ?? $reservation->email)
            : $reservation->email;
        if ($email === '' || $email === null) {
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

        $pdfBinary = $pdfGenerator->renderBinary($reservation);
        if ($pdfBinary === null || $pdfBinary === '') {
            Log::channel('single')->warning('SendFreeReservationConfirmationJob: PDF generation failed', [
                'reservation_id' => $reservation->id,
            ]);
            app()->setLocale($previousLocale);

            return;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'bus_free_');
        if ($tmpPath === false) {
            Log::channel('single')->error('SendFreeReservationConfirmationJob: tempnam failed', [
                'reservation_id' => $reservation->id,
            ]);
            app()->setLocale($previousLocale);

            return;
        }

        try {
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
        } catch (\Throwable $e) {
            @unlink($tmpPath);
            app()->setLocale($previousLocale);
            throw $e;
        }

        @unlink($tmpPath);
        app()->setLocale($previousLocale);

        $reservation->markConfirmationEmailSent();
    }
}
