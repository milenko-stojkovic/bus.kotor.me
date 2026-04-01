<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Services\Pdf\PaidInvoicePdfGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Generates invoice PDF for reservation. Fiscal (with fiscal data) or non-fiscal (with note).
 * Idempotent: ako već postoji PDF (invoice_pdf_path set i fajl postoji) – ne pravi novi (retry safe).
 * forceRegenerate: obriši postojeći fajl i generiši ponovo (npr. promjena tablica u panelu).
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
        public bool $isFiscal = true,
        public bool $forceRegenerate = false
    ) {}

    public function handle(PaidInvoicePdfGenerator $pdfGenerator): void
    {
        $reservation = Reservation::find($this->reservationId);
        if (! $reservation) {
            return;
        }

        // Invoice is always Montenegrin (cg) – never use app locale for PDF content
        $previousLocale = app()->getLocale();
        app()->setLocale('cg');

        try {
            $path = $reservation->invoice_pdf_path;

            if ($this->forceRegenerate && $path) {
                Storage::delete($path);
                $reservation->update(['invoice_pdf_path' => null]);
                $path = null;
            }

            // Idempotent: ako već postoji PDF – ne pravi novi
            if (! $this->forceRegenerate && $path && Storage::exists($path)) {
                return;
            }

            $relativePath = $pdfGenerator->generateAndStore($reservation, $this->isFiscal);

            if ($relativePath === null) {
                Log::channel('single')->warning('GenerateInvoicePdfJob: PDF generation returned null', [
                    'reservation_id' => $this->reservationId,
                    'is_fiscal' => $this->isFiscal,
                ]);

                return;
            }

            $reservation->update(['invoice_pdf_path' => $relativePath]);
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
}
