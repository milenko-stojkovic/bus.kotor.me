<?php

namespace App\Services\AdminPanel\Reports;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class AdminReportsCreatedAtBounds
{
    public function minDate(): Carbon
    {
        $minReservation = Reservation::query()->min('created_at');
        $minAdvance = null;
        if ((bool) config('features.advance_payments')) {
            $minAdvance = DB::table('agency_advance_transactions')->min('created_at');
        }

        $mins = array_values(array_filter([$minReservation, $minAdvance], fn ($v) => $v !== null));
        if (empty($mins)) {
            return now()->startOfDay();
        }

        return Carbon::parse(min($mins))->startOfDay();
    }

    public function maxDate(): Carbon
    {
        $maxReservation = Reservation::query()->max('created_at');
        $maxAdvance = null;
        if ((bool) config('features.advance_payments')) {
            $maxAdvance = DB::table('agency_advance_transactions')->max('created_at');
        }

        $maxs = array_values(array_filter([$maxReservation, $maxAdvance], fn ($v) => $v !== null));
        if (empty($maxs)) {
            return now()->startOfDay();
        }

        return Carbon::parse(max($maxs))->startOfDay();
    }
}

