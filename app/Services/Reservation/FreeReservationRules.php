<?php

namespace App\Services\Reservation;

use App\Models\ListOfTimeSlot;

/**
 * Jedinstvena backend pravila za "free" rezervaciju (bez banke).
 * Mora se poklapati sa UI preview logikom u ReservationBookingPageData / guest reserve.
 */
final class FreeReservationRules
{
    public static function isFreeReservation(ListOfTimeSlot $arrival, ListOfTimeSlot $departure): bool
    {
        $a = (int) $arrival->id;
        $d = (int) $departure->id;
        if ($a === 41 && $d === 1) {
            return false;
        }
        if (($a === 1 && ($d === 1 || $d === 41)) || ($a === 41 && $d === 41)) {
            return true;
        }

        return self::isFreeSlot($arrival) && self::isFreeSlot($departure);
    }

    /**
     * Da li slot pada u besplatni vremenski prozor (jutro/veče) — za UI kapacitet / disabled logiku.
     */
    public static function isFreeWindowSlot(ListOfTimeSlot $slot): bool
    {
        return self::isFreeSlot($slot);
    }

    private static function isFreeSlot(ListOfTimeSlot $slot): bool
    {
        $start = $slot->getStartTimeForDate(now()->startOfDay());
        $end = $slot->getEndTimeForDate(now()->startOfDay());
        if (! $start || ! $end) {
            return false;
        }

        $minutesStart = ((int) $start->format('H')) * 60 + (int) $start->format('i');
        $minutesEnd = ((int) $end->format('H')) * 60 + (int) $end->format('i');

        $inMorning = $minutesStart >= 0 && $minutesEnd <= 7 * 60;
        $inEvening = $minutesStart >= 20 * 60 && $minutesEnd <= 24 * 60;

        return $inMorning || $inEvening;
    }
}
