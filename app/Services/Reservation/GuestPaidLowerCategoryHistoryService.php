<?php

namespace App\Services\Reservation;

use App\Models\Reservation;
use App\Models\VehicleType;

/**
 * Shared lookup: most recent paid guest reservation for a normalized plate.
 */
final class GuestPaidLowerCategoryHistoryService
{
    public function findMostRecentPaidGuestReservation(
        string $normalizedPlate,
        ?int $excludeReservationId = null,
    ): ?Reservation {
        if ($normalizedPlate === '') {
            return null;
        }

        $query = Reservation::query()
            ->where('status', 'paid')
            ->whereNull('user_id')
            ->where('license_plate', $normalizedPlate)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->with('vehicleType');

        if ($excludeReservationId !== null) {
            $query->whereKeyNot($excludeReservationId);
        }

        return $query->first();
    }

    public function historicalPriceExceedsSubmitted(int $submittedVehicleTypeId, Reservation $historical): bool
    {
        $historical->loadMissing('vehicleType');
        $submitted = VehicleType::query()->find($submittedVehicleTypeId);
        if ($submitted === null || $historical->vehicleType === null) {
            return false;
        }

        $submittedPrice = (float) $submitted->price;
        $historicalPrice = (float) $historical->vehicleType->price;

        return $historicalPrice > $submittedPrice + 0.000001;
    }

    public function requiredCategoryLabel(Reservation $historical, string $locale): string
    {
        $historical->loadMissing('vehicleType');

        return $historical->vehicleType?->formatLabel($locale, 'EUR') ?? '#'.$historical->vehicle_type_id;
    }
}
