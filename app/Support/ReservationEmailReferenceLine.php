<?php

namespace App\Support;

use App\Models\Reservation;

/**
 * Plain-text reference line(s) for reservation/payment confirmation emails.
 */
final class ReservationEmailReferenceLine
{
    public static function forReservation(Reservation $reservation, string $locale): string
    {
        $mtid = trim((string) ($reservation->merchant_transaction_id ?? ''));
        if ($mtid !== '') {
            return self::transactionReference($mtid, $locale);
        }

        return self::reservationNumber((int) $reservation->id, $locale);
    }

    /**
     * @param  iterable<Reservation>  $reservations
     */
    public static function forReservations(iterable $reservations, string $locale): string
    {
        $lines = [];
        foreach ($reservations as $reservation) {
            $lines[] = self::forReservation($reservation, $locale);
        }

        return implode("\n", $lines);
    }

    public static function forMerchantTransactionId(?string $merchantTransactionId, string $locale): ?string
    {
        $mtid = trim((string) $merchantTransactionId);
        if ($mtid === '') {
            return null;
        }

        return self::transactionReference($mtid, $locale);
    }

    public static function appendBeforeClosing(string $body, ?string $referenceBlock): string
    {
        $referenceBlock = trim((string) $referenceBlock);
        if ($referenceBlock === '') {
            return $body;
        }

        $insertion = "\n\n".$referenceBlock;

        $markers = [
            "\n\nS poštovanjem,",
            "\n\nBest regards,",
            "\n\nThank you.",
            "\n\nHvala vam.",
            "\n\nThank you",
            "\n\nHvala",
        ];

        foreach ($markers as $marker) {
            $pos = strrpos($body, $marker);
            if ($pos !== false) {
                return substr_replace($body, $insertion, $pos, 0);
            }
        }

        return rtrim($body).$insertion;
    }

    private static function transactionReference(string $mtid, string $locale): string
    {
        $label = $locale === 'cg' ? 'Referenca transakcije' : 'Transaction reference';

        return "{$label}: {$mtid}";
    }

    private static function reservationNumber(int $reservationId, string $locale): string
    {
        $label = $locale === 'cg' ? 'Broj rezervacije' : 'Reservation number';

        return "{$label}: {$reservationId}";
    }
}
