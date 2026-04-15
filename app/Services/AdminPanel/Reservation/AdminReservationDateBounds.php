<?php

namespace App\Services\AdminPanel\Reservation;

use App\Models\Reservation;
use Carbon\Carbon;

/**
 * Granice kalendara za admin pretragu i edit (rezervacije).
 */
final class AdminReservationDateBounds
{
    public function searchMinDate(): Carbon
    {
        $min = Reservation::query()->min('reservation_date');

        return $min !== null
            ? Carbon::parse($min)->startOfDay()
            : now()->startOfDay();
    }

    public function searchMaxDate(): Carbon
    {
        return now()->copy()->addDays(90)->startOfDay();
    }

    /** Edit forma: danas .. danas+90 */
    public function editMinDate(): Carbon
    {
        return now()->startOfDay();
    }

    public function editMaxDate(): Carbon
    {
        return now()->copy()->addDays(90)->startOfDay();
    }
}
