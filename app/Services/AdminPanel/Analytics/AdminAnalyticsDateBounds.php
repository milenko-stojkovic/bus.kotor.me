<?php

namespace App\Services\AdminPanel\Analytics;

use App\Models\Reservation;
use App\Services\Reservation\PanelReservationListService;
use Carbon\Carbon;

final class AdminAnalyticsDateBounds
{
    /**
     * Minimum selectable date for Analytics "Datum od".
     *
     * Rule:
     * - oldest realized reservation date (realized by same definition as PanelReservationListService)
     * - fallback: oldest reservation date (any)
     * - fallback: today
     */
    public function minFromDate(): Carbon
    {
        $today = now()->startOfDay();

        // Any past reservation date is realized by definition (day < today).
        $minPast = Reservation::query()
            ->whereDate('reservation_date', '<', $today->toDateString())
            ->min('reservation_date');
        if ($minPast !== null) {
            return Carbon::parse($minPast)->startOfDay();
        }

        // Check if there is any realized reservation today (now past pick-up end).
        $todayRows = Reservation::query()
            ->with(['pickUpTimeSlot'])
            ->whereDate('reservation_date', $today->toDateString())
            ->orderBy('id')
            ->get();
        foreach ($todayRows as $r) {
            if (PanelReservationListService::isRealized($r)) {
                return $today;
            }
        }

        // Fallback: oldest reservation (any date).
        $minAny = Reservation::query()->min('reservation_date');
        if ($minAny !== null) {
            return Carbon::parse($minAny)->startOfDay();
        }

        return $today;
    }

    /** Maximum selectable date stays aligned with project calendars: today + 90 days. */
    public function maxToDate(): Carbon
    {
        return now()->copy()->addDays(90)->startOfDay();
    }
}

