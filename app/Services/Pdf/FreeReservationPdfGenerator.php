<?php

namespace App\Services\Pdf;

use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PDF potvrda besplatne rezervacije — sav sadržaj u šablonu na crnogorskom (cg), bez en varijante.
 * Logo: {@see public_path('images/logo_kotor.png')} — ako fajl ne postoji, PDF ide bez slike.
 * Generiše se na zahtev; ne čuva se trajno na disku.
 */
class FreeReservationPdfGenerator
{
    /**
     * PDF kao binarni sadržaj (bez čuvanja na disku).
     */
    public function renderBinary(Reservation $reservation): ?string
    {
        $previousLocale = app()->getLocale();
        app()->setLocale('cg');

        try {
            $reservation->loadMissing(['vehicleType.translations', 'dropOffTimeSlot', 'pickUpTimeSlot']);

            $vehicleLabel = 'N/A';
            if ($reservation->vehicleType) {
                $vehicleLabel = $reservation->vehicleType->getTranslatedDescription('cg')
                    ?: $reservation->vehicleType->getTranslatedName('cg')
                    ?: 'N/A';
            }

            $pdf = Pdf::loadView('pdf.free-reservation-confirmation', [
                'reservation' => $reservation,
                'logoDataUri' => KotorPdfAssets::logoDataUri(),
                'countryDisplay' => KotorPdfAssets::countryDisplayCg((string) $reservation->country),
                'vehicleTypeLabel' => $vehicleLabel,
            ])->setPaper('a4', 'portrait');

            return $pdf->output();
        } catch (Throwable $e) {
            Log::channel('single')->error('Free reservation PDF failed', [
                'reservation_id' => $reservation->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}
