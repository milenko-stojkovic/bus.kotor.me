<?php

namespace App\Services\AdminPanel\Reports;

use App\Models\Reservation;
use Carbon\Carbon;

final class AdminReportsCreatedAtBounds
{
    public function minDate(): Carbon
    {
        $min = Reservation::query()->min('created_at');
        if ($min === null) {
            return now()->startOfDay();
        }

        return Carbon::parse($min)->startOfDay();
    }

    public function maxDate(): Carbon
    {
        $max = Reservation::query()->max('created_at');
        if ($max === null) {
            return now()->startOfDay();
        }

        return Carbon::parse($max)->startOfDay();
    }
}

