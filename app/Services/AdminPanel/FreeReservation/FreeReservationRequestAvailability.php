<?php

namespace App\Services\AdminPanel\FreeReservation;

use App\Models\DailyParkingData;
use App\Models\FreeReservationRequest;
use Illuminate\Support\Collection;

class FreeReservationRequestAvailability
{
    /**
     * @return array{can_fulfill:bool,required:int,min_available:int|null,missing_daily_rows:bool}
     */
    public function forRequest(FreeReservationRequest $req): array
    {
        $required = (int) $req->vehicles()->count();
        if ($required < 1) {
            return [
                'can_fulfill' => false,
                'required' => $required,
                'min_available' => 0,
                'missing_daily_rows' => true,
            ];
        }

        $date = $req->reservation_date?->toDateString() ?? null;
        if (! is_string($date) || $date === '') {
            return [
                'can_fulfill' => false,
                'required' => $required,
                'min_available' => null,
                'missing_daily_rows' => true,
            ];
        }

        $drop = (int) $req->drop_off_time_slot_id;
        $pick = (int) $req->pick_up_time_slot_id;
        $slotIds = array_values(array_unique([$drop, $pick]));

        /** @var Collection<int, DailyParkingData> $rows */
        $rows = DailyParkingData::query()
            ->whereDate('date', $date)
            ->whereIn('time_slot_id', $slotIds)
            ->get()
            ->keyBy('time_slot_id');

        $minAvailable = null;
        foreach ($slotIds as $slotId) {
            $row = $rows->get($slotId);
            if (! $row || (bool) $row->is_blocked) {
                return [
                    'can_fulfill' => false,
                    'required' => $required,
                    'min_available' => 0,
                    'missing_daily_rows' => $row === null,
                ];
            }
            $avail = (int) $row->availableCapacity();
            $minAvailable = $minAvailable === null ? $avail : min($minAvailable, $avail);
        }

        return [
            'can_fulfill' => ($minAvailable !== null) && $required <= $minAvailable,
            'required' => $required,
            'min_available' => $minAvailable,
            'missing_daily_rows' => false,
        ];
    }
}

