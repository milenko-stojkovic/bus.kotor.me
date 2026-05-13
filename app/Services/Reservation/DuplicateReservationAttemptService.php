<?php

namespace App\Services\Reservation;

use App\Models\Reservation;

class DuplicateReservationAttemptService
{
    public static function normalizeLicensePlate(?string $licensePlate): string
    {
        $v = strtoupper(trim((string) $licensePlate));
        // Tolerate common Montenegrin/Serbian Latin diacritics in manual input.
        // OCR normalization is also ASCII-only; keep backend tolerant so that manual input isn't rejected.
        $v = strtr($v, [
            'Ž' => 'Z',
            'Š' => 'S',
            'Č' => 'C',
            'Ć' => 'C',
            'Đ' => 'D',
        ]);
        $v = preg_replace('/\s+/', '', $v) ?? $v;
        $v = preg_replace('/[^A-Z0-9]/', '', $v) ?? $v;

        return $v;
    }

    /**
     * Duplicate attempt definition:
     * - same reservation_date
     * - same normalized license_plate
     * - same drop_off_time_slot_id OR same pick_up_time_slot_id
     *
     * Cross-match (drop=pick / pick=drop) is intentionally NOT counted.
     */
    public function existsConflict(string $date, string $licensePlate, int $dropOffSlotId, int $pickUpSlotId): bool
    {
        $plate = self::normalizeLicensePlate($licensePlate);
        if ($plate === '') {
            return false;
        }

        return Reservation::query()
            ->whereDate('reservation_date', $date)
            ->where('license_plate', $plate)
            ->where(function ($q) use ($dropOffSlotId, $pickUpSlotId) {
                $q->where('drop_off_time_slot_id', $dropOffSlotId)
                    ->orWhere('pick_up_time_slot_id', $pickUpSlotId);
            })
            ->exists();
    }
}

