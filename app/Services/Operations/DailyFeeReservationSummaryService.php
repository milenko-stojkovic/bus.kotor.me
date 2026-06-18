<?php

namespace App\Services\Operations;

use App\Models\Reservation;
use App\Support\ReservationKind;
use Carbon\Carbon;

/**
 * Read-only counts of confirmed Daily fee (daily_ticket) reservations per calendar day.
 */
final class DailyFeeReservationSummaryService
{
    /**
     * @return array{date: string, total: int}
     */
    public function forDate(Carbon $day): array
    {
        $date = $day->copy()->startOfDay()->toDateString();

        return [
            'date' => $date,
            'total' => $this->confirmedDailyTicketCount($date),
        ];
    }

    /**
     * @return array{today: array{date: string, total: int}, tomorrow: array{date: string, total: int}, timezone: string}
     */
    public function todayAndTomorrow(): array
    {
        $tz = (string) config('reservations.operations_timezone', 'Europe/Podgorica');
        $today = Carbon::now($tz)->startOfDay();
        $tomorrow = $today->copy()->addDay();

        return [
            'timezone' => $tz,
            'today' => $this->forDate($today),
            'tomorrow' => $this->forDate($tomorrow),
        ];
    }

    private function confirmedDailyTicketCount(string $date): int
    {
        return (int) Reservation::query()
            ->whereDate('reservation_date', $date)
            ->where('reservation_kind', ReservationKind::DAILY_TICKET)
            ->where('status', 'paid')
            ->count();
    }
}
