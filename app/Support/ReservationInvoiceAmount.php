<?php

namespace App\Support;

use App\Models\VehicleType;

/**
 * Snapshot iznosa računa u trenutku kreiranja rezervacije (ne iz PDF generatora).
 */
final class ReservationInvoiceAmount
{
    /**
     * @param  'paid'|'free'  $status
     */
    public static function snapshotForNewReservation(string $status, ?int $vehicleTypeId): string
    {
        if ($status === 'free') {
            return '0.00';
        }

        if ($vehicleTypeId === null) {
            return '0.00';
        }

        $price = VehicleType::query()->whereKey($vehicleTypeId)->value('price');

        return number_format((float) ($price ?? 0), 2, '.', '');
    }
}
