<?php

namespace App\Support;

use App\Models\Reservation;

/**
 * Canonical reservation PDF attachment/download filenames (V1-compatible).
 */
final class ReservationPdfFilename
{
    public static function forReservation(Reservation $reservation): string
    {
        if ((string) $reservation->status === 'free') {
            return self::freeConfirmation($reservation);
        }

        return self::invoice($reservation);
    }

    public static function invoice(Reservation $reservation): string
    {
        return sprintf('invoice-%d-%s.pdf', (int) $reservation->id, self::dateSegment($reservation));
    }

    public static function freeConfirmation(Reservation $reservation): string
    {
        return sprintf('free-confirmation-%d-%s.pdf', (int) $reservation->id, self::dateSegment($reservation));
    }

    public static function dateSegment(Reservation $reservation): string
    {
        return $reservation->reservation_date?->format('Y-m-d')
            ?? $reservation->created_at?->format('Y-m-d')
            ?? now()->format('Y-m-d');
    }
}
