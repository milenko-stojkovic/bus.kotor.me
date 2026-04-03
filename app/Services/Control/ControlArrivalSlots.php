<?php

namespace App\Services\Control;

use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use Carbon\Carbon;

/**
 * Dolasci za control panel: vidljivo od (početak termina − N sati) do kraja termina, po kalendarskom danu rezervacije.
 */
final class ControlArrivalSlots
{
    /**
     * @param  int  $hoursBeforeStart  Koliko sati prije početka termina počinje prikaz (default 3).
     * @return list<array{at: Carbon, label: string, reservations: \Illuminate\Database\Eloquent\Collection<int, Reservation>}>
     */
    public function groupsWithinNextHours(int $hoursBeforeStart = 3): array
    {
        $tz = (string) config('reservations.operations_timezone', 'Europe/Podgorica');
        $now = Carbon::now($tz);
        $today = $now->copy()->startOfDay();
        $tomorrow = $today->copy()->addDay();

        $slots = ListOfTimeSlot::query()->orderBy('id')->get();

        $groups = [];

        foreach ([$today, $tomorrow] as $day) {
            foreach ($slots as $slot) {
                if (! $slot->isInArrivalControlWindow($now, $day, $hoursBeforeStart)) {
                    continue;
                }

                $start = $slot->getStartTimeForDate($day);
                if ($start === null) {
                    continue;
                }

                $reservations = Reservation::query()
                    ->whereDate('reservation_date', $day->format('Y-m-d'))
                    ->where(function ($q) use ($slot) {
                        $q->where('drop_off_time_slot_id', $slot->id)
                            ->orWhere('pick_up_time_slot_id', $slot->id);
                    })
                    ->with(['vehicleType.translations'])
                    ->orderBy('license_plate')
                    ->get();

                if ($reservations->isEmpty()) {
                    continue;
                }

                $groups[] = [
                    'at' => $start,
                    'label' => $slot->time_slot,
                    'reservations' => $reservations,
                ];
            }
        }

        usort($groups, fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        return $groups;
    }
}
