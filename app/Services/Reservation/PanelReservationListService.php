<?php

namespace App\Services\Reservation;

use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Predstojeće vs realizovane rezervacije za agency panel (isti korisnik).
 * Upcoming: datum u budućnosti ILI (danas i now < kraj departure slota).
 * Realized: komplement.
 */
final class PanelReservationListService
{
    /**
     * @return Collection<int, Reservation>
     */
    public function upcomingFor(User $user): Collection
    {
        return $this->baseQuery($user)
            ->get()
            ->filter(fn (Reservation $r) => self::isUpcoming($r))
            ->values();
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function realizedFor(User $user): Collection
    {
        return $this->baseQuery($user)
            ->get()
            ->filter(fn (Reservation $r) => self::isRealized($r))
            ->sortByDesc(function (Reservation $r): int {
                $end = self::pickUpEndForSort($r);

                return $end ? $end->getTimestamp() : $r->reservation_date->getTimestamp();
            })
            ->values();
    }

    public static function isUpcoming(Reservation $r): bool
    {
        $day = $r->reservation_date->copy()->startOfDay();
        $today = now()->startOfDay();

        if ($day->gt($today)) {
            return true;
        }
        if ($day->lt($today)) {
            return false;
        }

        $end = $r->pickUpTimeSlot?->getEndTimeForDate($day);
        if ($end === null) {
            return true;
        }

        return now()->lt($end);
    }

    public static function isRealized(Reservation $r): bool
    {
        return ! self::isUpcoming($r);
    }

    /** Kraj departure (pick-up) termina za sortiranje realizovanih (najnovije prvo). */
    public static function pickUpEndForSort(Reservation $r): ?Carbon
    {
        $day = $r->reservation_date->copy()->startOfDay();

        return $r->pickUpTimeSlot?->getEndTimeForDate($day);
    }

    /**
     * @return Builder<Reservation>
     */
    private function baseQuery(User $user): Builder
    {
        return Reservation::query()
            ->where('user_id', $user->id)
            ->with(['pickUpTimeSlot', 'dropOffTimeSlot', 'vehicleType.translations'])
            ->orderBy('reservation_date', 'desc')
            ->orderBy('id', 'desc');
    }
}
