<?php

namespace App\Services\AdminPanel\Blocking;

use App\Models\ListOfTimeSlot;
use Illuminate\Support\Collection;

/**
 * Grupiše ID-jeve slotova u dane: ceo katalog pokriven ili lista spojenih vremenskih raspona.
 * Uzastopni slotovi (po rastućem id) spajaju se u jedan raspon: početak prvog – kraj poslednjeg.
 */
final class DaySlotRangeSummaryBuilder
{
    /**
     * @param  Collection<int, ListOfTimeSlot>  $orderedSlots  svi slotovi, tipično orderBy id
     * @param  list<int>  $selectedSlotIds
     * @return array{is_full_day: bool, ranges: list<string>}
     */
    public function summarize(Collection $orderedSlots, array $selectedSlotIds): array
    {
        $catalogIds = $orderedSlots->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $unique = array_values(array_unique(array_map('intval', $selectedSlotIds)));
        sort($unique);

        if ($catalogIds === []) {
            return ['is_full_day' => false, 'ranges' => []];
        }

        $isFullDay = $unique === $catalogIds;
        if ($isFullDay) {
            return ['is_full_day' => true, 'ranges' => []];
        }

        $slotsById = $orderedSlots->keyBy('id');

        return [
            'is_full_day' => false,
            'ranges' => $this->mergeConsecutiveIdsToLabels($unique, $slotsById),
        ];
    }

    /**
     * @param  list<int>  $sortedIds
     * @param  Collection<int, ListOfTimeSlot>  $slotsById
     * @return list<string>
     */
    private function mergeConsecutiveIdsToLabels(array $sortedIds, Collection $slotsById): array
    {
        if ($sortedIds === []) {
            return [];
        }

        $ranges = [];
        $blockStart = $sortedIds[0];
        $prev = $sortedIds[0];
        $n = count($sortedIds);
        for ($i = 1; $i < $n; $i++) {
            $id = $sortedIds[$i];
            if ($id === $prev + 1) {
                $prev = $id;

                continue;
            }
            $ranges[] = $this->formatSpan($blockStart, $prev, $slotsById);
            $blockStart = $id;
            $prev = $id;
        }
        $ranges[] = $this->formatSpan($blockStart, $prev, $slotsById);

        return $ranges;
    }

    private function formatSpan(int $startId, int $endId, Collection $slotsById): string
    {
        $first = $slotsById->get($startId);
        $last = $slotsById->get($endId);
        if ($first === null || $last === null) {
            return '#'.$startId.($startId !== $endId ? ' … #'.$endId : '');
        }
        if ($startId === $endId) {
            return $first->time_slot;
        }

        $startPart = $this->timeSlotStart($first->time_slot);
        $endPart = $this->timeSlotEnd($last->time_slot);
        if ($startPart === null || $endPart === null) {
            return $first->time_slot.' … '.$last->time_slot;
        }

        return $startPart.' - '.$endPart;
    }

    private function timeSlotStart(string $timeSlot): ?string
    {
        $parts = explode(' - ', $timeSlot, 2);
        $s = trim($parts[0] ?? '');

        return $s !== '' ? $s : null;
    }

    private function timeSlotEnd(string $timeSlot): ?string
    {
        $parts = explode(' - ', $timeSlot, 2);
        $s = trim($parts[1] ?? '');

        return $s !== '' ? $s : null;
    }
}
