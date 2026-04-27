<?php

namespace App\Services\AdminPanel\FreeReservation;

use App\Models\DailyParkingData;
use App\Models\FreeReservationRequest;
use App\Models\FreeReservationRequestSegment;
use Illuminate\Support\Collection;

class FreeReservationRequestAvailability
{
    /**
     * @return array{can_fulfill:bool,required:int,min_available:int|null,missing_daily_rows:bool,segments:list<array{segment_id:int,can_fulfill:bool,required:int,min_available:int|null,missing_daily_rows:bool}>}
     */
    public function forRequest(FreeReservationRequest $req): array
    {
        $req->loadMissing(['segments.vehicles']);
        /** @var \Illuminate\Support\Collection<int, FreeReservationRequestSegment> $segments */
        $segments = $req->segments;

        $date = $req->reservation_date?->toDateString() ?? null;
        if (! is_string($date) || $date === '' || $segments->count() < 1) {
            return [
                'can_fulfill' => false,
                'required' => 0,
                'min_available' => null,
                'missing_daily_rows' => true,
                'segments' => [],
            ];
        }

        // Demand by slot across all segments (for overall "all or nothing").
        $demandBySlot = [];
        $segmentSummaries = [];

        foreach ($segments as $seg) {
            $required = (int) $seg->vehicles->count();
            $drop = (int) $seg->drop_off_time_slot_id;
            $pick = (int) $seg->pick_up_time_slot_id;
            $slotIds = array_values(array_unique([$drop, $pick]));
            foreach ($slotIds as $slotId) {
                $demandBySlot[$slotId] = (int) ($demandBySlot[$slotId] ?? 0) + $required;
            }

            $segmentSummaries[] = [
                'segment_id' => (int) $seg->id,
                'can_fulfill' => false,
                'required' => $required,
                'min_available' => null,
                'missing_daily_rows' => false,
            ];
        }

        $slotIdsAll = array_values(array_unique(array_map('intval', array_keys($demandBySlot))));
        sort($slotIdsAll);

        /** @var Collection<int, DailyParkingData> $rows */
        $rows = DailyParkingData::query()
            ->whereDate('date', $date)
            ->whereIn('time_slot_id', $slotIdsAll)
            ->get()
            ->keyBy('time_slot_id');

        $overallMin = null;
        foreach ($slotIdsAll as $slotId) {
            $row = $rows->get($slotId);
            if (! $row || (bool) $row->is_blocked) {
                return [
                    'can_fulfill' => false,
                    'required' => array_sum(array_map('intval', $demandBySlot)),
                    'min_available' => 0,
                    'missing_daily_rows' => $row === null,
                    'segments' => $segmentSummaries,
                ];
            }
            $avail = (int) $row->availableCapacity();
            $overallMin = $overallMin === null ? $avail : min($overallMin, $avail);
        }

        // Compute per-segment min availability (informational).
        foreach ($segmentSummaries as $idx => $summary) {
            $seg = $segments->firstWhere('id', $summary['segment_id']);
            if (! $seg) {
                continue;
            }

            $required = (int) $seg->vehicles->count();
            $drop = (int) $seg->drop_off_time_slot_id;
            $pick = (int) $seg->pick_up_time_slot_id;
            $slotIds = array_values(array_unique([$drop, $pick]));

            $min = null;
            foreach ($slotIds as $slotId) {
                $row = $rows->get($slotId);
                if (! $row || (bool) $row->is_blocked) {
                    $segmentSummaries[$idx]['can_fulfill'] = false;
                    $segmentSummaries[$idx]['min_available'] = 0;
                    $segmentSummaries[$idx]['missing_daily_rows'] = $row === null;
                    continue 2;
                }
                $avail = (int) $row->availableCapacity();
                $min = $min === null ? $avail : min($min, $avail);
            }

            $segmentSummaries[$idx]['min_available'] = $min;
            $segmentSummaries[$idx]['can_fulfill'] = ($min !== null) && $required <= $min;
        }

        // Overall check uses combined demand per slot.
        $canOverall = true;
        foreach ($demandBySlot as $slotId => $demand) {
            $row = $rows->get((int) $slotId);
            if (! $row || (bool) $row->is_blocked || (int) $row->availableCapacity() < (int) $demand) {
                $canOverall = false;
                break;
            }
        }

        return [
            'can_fulfill' => $canOverall,
            'required' => array_sum(array_map('intval', $demandBySlot)),
            'min_available' => $overallMin,
            'missing_daily_rows' => false,
            'segments' => $segmentSummaries,
        ];
    }
}

