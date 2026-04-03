<?php

namespace App\Services\Pdf;

use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fiskalni ili nefiskalni račun (plaćena rezervacija) — PDF po V1 izgledu.
 * Sav vidljivi tekst u šablonu je na crnogorskom (cg); nema en varijante (zvanični izdavač).
 * Iznos isključivo iz {@see Reservation::$invoice_amount} (snapshot u bazi).
 */
class PaidInvoicePdfGenerator
{
    public const NON_FISCAL_NOTE = 'Račun je važeći kao potvrda o kupovini termina. Fiskalizovani račun biće dostavljen naknadno.';

    /**
     * PDF kao binarni sadržaj (bez čuvanja na disku).
     */
    public function renderBinary(Reservation $reservation, bool $isFiscal): ?string
    {
        $previousLocale = app()->getLocale();
        app()->setLocale('cg');

        try {
            $reservation->loadMissing(['vehicleType.translations', 'dropOffTimeSlot', 'pickUpTimeSlot']);

            $vehicleLine = 'Naknada';
            if ($reservation->vehicleType) {
                $vehicleLine = $reservation->vehicleType->getTranslatedDescription('cg')
                    ?: $reservation->vehicleType->getTranslatedName('cg')
                    ?: 'Naknada';
            }

            $unitPrice = (float) $reservation->invoice_amount;

            $fiscalDateTime = $reservation->created_at
                ? Carbon::parse($reservation->created_at)
                : now();

            $qrDataUri = $isFiscal
                ? KotorPdfAssets::fiscalVerificationQrDataUri($reservation->fiscal_qr)
                : null;

            $internalNumber = KotorPdfAssets::parseInternalNumberFromFiscalQr($reservation->fiscal_qr);

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
                'nonFiscalNote' => self::NON_FISCAL_NOTE,
            ])->setPaper('a4', 'portrait');

            return $pdf->output();
        } catch (Throwable $e) {
            Log::channel('single')->error('Paid invoice PDF failed', [
                'reservation_id' => $reservation->id,
                'is_fiscal' => $isFiscal,
                'message' => $e->getMessage(),
            ]);

            return null;
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}
