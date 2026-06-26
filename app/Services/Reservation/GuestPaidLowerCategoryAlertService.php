<?php

namespace App\Services\Reservation;

use App\Models\Reservation;
use App\Services\AdminFiscalizationAlertService;
use App\Services\AdminPanel\AdminAlertService;

/**
 * Safety net after a guest paid reservation is created: alert if category price is
 * lower than the most recent paid guest reservation for the same plate.
 * Normal flow is blocked at checkout by GuestPaidLowerCategoryCheckoutGuard.
 */
final class GuestPaidLowerCategoryAlertService
{
    public function __construct(
        private readonly GuestPaidLowerCategoryHistoryService $history,
    ) {}

    public function evaluate(Reservation $reservation): void
    {
        if ($reservation->status !== 'paid' || $reservation->user_id !== null) {
            return;
        }

        $normalizedPlate = DuplicateReservationAttemptService::normalizeLicensePlate($reservation->license_plate);
        if ($normalizedPlate === '') {
            return;
        }

        $historical = $this->history->findMostRecentPaidGuestReservation(
            $normalizedPlate,
            (int) $reservation->id,
        );
        if ($historical === null) {
            return;
        }

        if (! $this->history->historicalPriceExceedsSubmitted((int) $reservation->vehicle_type_id, $historical)) {
            return;
        }

        $reservation->loadMissing('vehicleType');
        $historical->loadMissing('vehicleType');

        $newPrice = (float) ($reservation->vehicleType?->price ?? 0);
        $historicalPrice = (float) ($historical->vehicleType?->price ?? 0);

        $this->notifyAdmin($reservation, $historical, $normalizedPlate, $newPrice, $historicalPrice);
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
            'A new guest paid reservation uses a lower vehicle category price than the most recent historical paid guest reservation for the same license plate.',
            'Checkout should normally block this case; treat as safety-net review.',
            '',
            '--- new reservation ---',
            $fiscalAlerts->buildReservationContext($reservation),
            'vehicle_type.price: '.number_format($newPrice, 2, '.', ''),
            '',
            '--- historical paid guest reservation ---',
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
