<?php

namespace App\Services\Reservation;

use App\Models\Reservation;
use App\Services\AdminFiscalizationAlertService;
use App\Services\AdminPanel\AdminAlertService;

/**
 * After a guest paid reservation is created, compare its vehicle category price
 * against the most recent older paid reservation for the same normalized plate.
 * Informational only — does not block checkout, payment, or fiscalization.
 */
final class GuestPaidLowerCategoryAlertService
{
    public function evaluate(Reservation $reservation): void
    {
        if ($reservation->status !== 'paid' || $reservation->user_id !== null) {
            return;
        }

        $normalizedPlate = DuplicateReservationAttemptService::normalizeLicensePlate($reservation->license_plate);
        if ($normalizedPlate === '') {
            return;
        }

        $historical = $this->findMostRecentHistoricalPaidReservation($reservation, $normalizedPlate);
        if ($historical === null) {
            return;
        }

        $reservation->loadMissing('vehicleType');
        $historical->loadMissing('vehicleType');

        $newPrice = (float) ($reservation->vehicleType?->price ?? 0);
        $historicalPrice = (float) ($historical->vehicleType?->price ?? 0);

        if ($newPrice >= $historicalPrice - 0.000001) {
            return;
        }

        $this->notifyAdmin($reservation, $historical, $normalizedPlate, $newPrice, $historicalPrice);
    }

    private function findMostRecentHistoricalPaidReservation(
        Reservation $reservation,
        string $normalizedPlate,
    ): ?Reservation {
        return Reservation::query()
            ->where('status', 'paid')
            ->whereKeyNot($reservation->id)
            ->where('license_plate', $normalizedPlate)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->with('vehicleType')
            ->first();
    }

    private function notifyAdmin(
        Reservation $reservation,
        Reservation $historical,
        string $normalizedPlate,
        float $newPrice,
        float $historicalPrice,
    ): void {
        $fiscalAlerts = app(AdminFiscalizationAlertService::class);

        $subject = '[Kotor Bus] Guest paid reservation: lower vehicle category than historical paid';

        $body = implode("\n", [
            'A new guest paid reservation uses a lower vehicle category price than the most recent historical paid reservation for the same license plate.',
            'The reservation was NOT blocked; this is informational for manual review.',
            '',
            '--- new reservation ---',
            $fiscalAlerts->buildReservationContext($reservation),
            'vehicle_type.price: '.number_format($newPrice, 2, '.', ''),
            '',
            '--- historical paid reservation ---',
            $fiscalAlerts->buildReservationContext($historical),
            'vehicle_type.price: '.number_format($historicalPrice, 2, '.', ''),
            '',
            'normalized_plate: '.$normalizedPlate,
        ]);

        $alert = app(AdminAlertService::class)->createOnce(
            'guest_paid_lower_category_than_history',
            'Guest plaćena rezervacija: niža kategorija od ranije plaćene',
            sprintf(
                'Tablica %s: nova guest plaćena rezervacija #%d (kategorija %.2f EUR) ispod ranije plaćene #%d (%.2f EUR).',
                $normalizedPlate,
                $reservation->id,
                $newPrice,
                $historical->id,
                $historicalPrice,
            ),
            'medium',
            'guest_paid_lower_category:'.$reservation->id,
            [
                'reservation_id' => $reservation->id,
                'historical_reservation_id' => $historical->id,
                'license_plate' => $normalizedPlate,
                'new_vehicle_type_id' => $reservation->vehicle_type_id,
                'historical_vehicle_type_id' => $historical->vehicle_type_id,
                'new_price' => $newPrice,
                'historical_price' => $historicalPrice,
                'email_full_body' => $body,
            ],
        );

        if ($alert !== null) {
            $alert->update(['reservation_id' => $reservation->id]);
        }

        $fiscalAlerts->notify($subject, $body, [
            'alert_type' => 'guest_paid_lower_category_than_history',
            'reservation_id' => $reservation->id,
            'historical_reservation_id' => $historical->id,
        ]);

        \Illuminate\Support\Facades\Log::channel('payments')->info('guest_paid_lower_category_alert', [
            'reservation_id' => $reservation->id,
            'historical_reservation_id' => $historical->id,
            'license_plate' => $normalizedPlate,
            'new_price' => $newPrice,
            'historical_price' => $historicalPrice,
        ]);
    }
}
