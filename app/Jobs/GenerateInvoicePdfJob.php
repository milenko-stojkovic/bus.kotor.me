<?php

namespace App\Jobs;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Generates invoice PDF for reservation. Fiscal (with fiscal data) or non-fiscal (with note).
 * Idempotent: ako već postoji PDF (invoice_pdf_path set i fajl postoji) – ne pravi novi (retry safe).
 *
 * Invoice (PDF) & fiscalization: ALWAYS in Montenegrin (cg). Legal requirement (local government issuer).
 * Do NOT use app locale or __() for invoice content – all strings are hardcoded in Montenegrin.
 */
class GenerateInvoicePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const NON_FISCAL_NOTE = 'Račun je važeći kao potvrda o kupovini termina. Fiskalizovani račun biće dostavljen naknadno.';

    public int $tries = 2;

    public int $timeout = 45;

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

        // Invoice is always Montenegrin (cg) – never use app locale for PDF content
        $previousLocale = app()->getLocale();
        app()->setLocale('cg');

        try {
            // Idempotent: ako već postoji PDF – ne pravi novi
            $path = $reservation->invoice_pdf_path;
            if ($path && Storage::exists($path)) {
                return;
            }

            $dir = 'invoices';
            Storage::makeDirectory($dir);
            $path = $dir.'/'.$this->reservationId.'.pdf';

            $content = $this->isFiscal
                ? $this->buildFiscalContent($reservation)
                : $this->buildNonFiscalContent($reservation);

            // TODO: replace with real PDF generation (e.g. dompdf/snappy); for now store text placeholder
            Storage::put($path, $content);

            $reservation->update(['invoice_pdf_path' => $path]);
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    public static function invoicePath(int $reservationId): string
    {
        return storage_path('app/invoices/'.$reservationId.'.pdf');
    }

    /** Pun put do PDF fajla (iz reservation.invoice_pdf_path ili default). */
    public static function fullPathForReservation(Reservation $reservation): string
    {
        if ($reservation->invoice_pdf_path) {
            return storage_path('app/'.$reservation->invoice_pdf_path);
        }

        return self::invoicePath($reservation->id);
    }

    private function buildFiscalContent(Reservation $reservation): string
    {
        $lines = [
            'Potvrda rezervacije #'.$reservation->id,
            'Datum: '.$reservation->reservation_date->format('Y-m-d'),
            'JIR: '.($reservation->fiscal_jir ?? '-'),
            'IKOF: '.($reservation->fiscal_ikof ?? '-'),
        ];

        return implode("\n", $lines);
    }

    private function buildNonFiscalContent(Reservation $reservation): string
    {
        $lines = [
            'Potvrda rezervacije #'.$reservation->id,
            'Datum: '.$reservation->reservation_date->format('Y-m-d'),
            '',
            self::NON_FISCAL_NOTE,
        ];

        return implode("\n", $lines);
    }
}
