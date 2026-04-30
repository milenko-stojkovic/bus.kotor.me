<?php

namespace App\Services\Reservation;

use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;

final class PanelStatisticsDateBounds
{
    /**
     * min date:
     * - earliest realized reservation_date for this user
     * - fallback: earliest reservation_date for this user
     * - fallback: today
     */
    public function minDateFor(User $user): Carbon
    {
        $tz = (string) config('reservations.operations_timezone', 'Europe/Podgorica');
        $today = Carbon::today($tz);

        // Any reservation in the past is always realized.
        $minRealizedPast = Reservation::query()
            ->where('user_id', $user->id)
            ->whereDate('reservation_date', '<', $today->toDateString())
            ->min('reservation_date');
        if (is_string($minRealizedPast) && $minRealizedPast !== '') {
            return Carbon::parse($minRealizedPast, $tz)->startOfDay();
        }

        // If user has only today's reservations, realized depends on pick-up end time.
        $todayRealizedMin = Reservation::query()
            ->where('user_id', $user->id)
            ->whereDate('reservation_date', '=', $today->toDateString())
            ->with(['pickUpTimeSlot'])
            ->get()
            ->filter(fn (Reservation $r) => PanelReservationListService::isRealized($r))
            ->min(fn (Reservation $r) => $r->reservation_date?->toDateString());
        if (is_string($todayRealizedMin) && $todayRealizedMin !== '') {
            return Carbon::parse($todayRealizedMin, $tz)->startOfDay();
        }

        $minAny = Reservation::query()
            ->where('user_id', $user->id)
            ->min('reservation_date');
        if (is_string($minAny) && $minAny !== '') {
            return Carbon::parse($minAny, $tz)->startOfDay();
        }

        return $today->startOfDay();
    }

    /**
     * max date:
     * - today + 90 days
     */
    public function maxDateFor(User $user): Carbon
    {
        $tz = (string) config('reservations.operations_timezone', 'Europe/Podgorica');

        return Carbon::today($tz)->addDays(90)->startOfDay();
    }
}

