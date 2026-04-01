<?php

namespace App\Services\Pdf;

use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Fiskalni ili nefiskalni račun (plaćena rezervacija) — PDF po V1 izgledu.
 * Sav vidljivi tekst u šablonu je na crnogorskom (cg); nema en varijante (zvanični izdavač).
 */
class PaidInvoicePdfGenerator
{
    public function generateAndStore(Reservation $reservation, bool $isFiscal): ?string
    {
        $reservation->loadMissing(['vehicleType.translations', 'dropOffTimeSlot', 'pickUpTimeSlot']);

        $vehicleLine = 'Naknada';
        if ($reservation->vehicleType) {
            $vehicleLine = $reservation->vehicleType->getTranslatedDescription('cg')
                ?: $reservation->vehicleType->getTranslatedName('cg')
                ?: 'Naknada';
        }

        $unitPrice = (float) ($reservation->vehicleType->price ?? 0);

        $fiscalDateTime = $reservation->created_at
            ? Carbon::parse($reservation->created_at)
            : now();

        $qrDataUri = $isFiscal
            ? KotorPdfAssets::fiscalVerificationQrDataUri($reservation->fiscal_qr)
            : null;

        $internalNumber = KotorPdfAssets::parseInternalNumberFromFiscalQr($reservation->fiscal_qr);

        try {
            $pdf = Pdf::loadView('pdf.paid-invoice', [
                'reservation' => $reservation,
                'isFiscal' => $isFiscal,
                'logoDataUri' => KotorPdfAssets::logoDataUri(),
                'qrDataUri' => $qrDataUri,
                'countryDisplay' => KotorPdfAssets::countryDisplayCg((string) $reservation->country),
                'vehicleLine' => $vehicleLine,
                'unitPrice' => $unitPrice,
                'fiscalDateTime' => $fiscalDateTime,
                'internalNumber' => $internalNumber,
                'nonFiscalNote' => GenerateInvoicePdfJob::NON_FISCAL_NOTE,
            ])->setPaper('a4', 'portrait');

            $dir = 'invoices';
            Storage::makeDirectory($dir);
            $relativePath = $dir.'/'.$reservation->id.'.pdf';
            $fullPath = storage_path('app/'.$relativePath);
            $pdf->save($fullPath);

            return $relativePath;
        } catch (Throwable $e) {
            Log::channel('single')->error('Paid invoice PDF failed', [
                'reservation_id' => $reservation->id,
                'is_fiscal' => $isFiscal,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
