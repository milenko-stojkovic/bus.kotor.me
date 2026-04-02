<?php

namespace App\Services\Pdf;

use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * PDF potvrda besplatne rezervacije — sav sadržaj u šablonu na crnogorskom (cg), bez en varijante.
 * Logo: {@see public_path('images/logo_kotor.png')} — ako fajl ne postoji, PDF ide bez slike.
 */
class FreeReservationPdfGenerator
{
    public function generateAndStore(Reservation $reservation): ?string
    {
        $reservation->loadMissing(['vehicleType.translations', 'dropOffTimeSlot', 'pickUpTimeSlot']);

        $vehicleLabel = 'N/A';
        if ($reservation->vehicleType) {
            $vehicleLabel = $reservation->vehicleType->getTranslatedDescription('cg')
                ?: $reservation->vehicleType->getTranslatedName('cg')
                ?: 'N/A';
        }

        try {
            $pdf = Pdf::loadView('pdf.free-reservation-confirmation', [
                'reservation' => $reservation,
                'logoDataUri' => KotorPdfAssets::logoDataUri(),
                'countryDisplay' => KotorPdfAssets::countryDisplayCg((string) $reservation->country),
                'vehicleTypeLabel' => $vehicleLabel,
            ])->setPaper('a4', 'portrait');

            $dir = storage_path('app/invoices');
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (! is_dir($dir)) {
                throw new \RuntimeException('Failed to create invoices directory: '.$dir);
            }

            $relativePath = 'invoices/'.$reservation->id.'.pdf';
            $fullPath = $dir.DIRECTORY_SEPARATOR.$reservation->id.'.pdf';
            $pdf->save($fullPath);

            return $relativePath;
        } catch (Throwable $e) {
            Log::channel('single')->error('Free reservation PDF failed', [
                'reservation_id' => $reservation->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
