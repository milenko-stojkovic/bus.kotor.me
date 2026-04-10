<?php

namespace App\Services\AdminPanel\Blocking;

use App\Models\BlockZoneWorklist;
use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\TempData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BlockingService
{
    /**
     * @return Collection<int, ListOfTimeSlot>
     */
    public function allSlots(): Collection
    {
        return ListOfTimeSlot::query()->orderBy('id')->get();
    }

    /**
     * @param  list<int>  $slotIds
     */
    public function applyBlock(string $date, array $slotIds): void
    {
        $slotIds = array_values(array_unique(array_map('intval', $slotIds)));
        $slotIds = array_values(array_filter($slotIds, fn (int $id) => $id > 0));
        if ($slotIds === []) {
            return;
        }

        DB::transaction(function () use ($date, $slotIds): void {
            $daily = DailyParkingData::query()
                ->where('date', $date)
                ->whereIn('time_slot_id', $slotIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('time_slot_id');

            foreach ($slotIds as $slotId) {
                /** @var DailyParkingData|null $row */
                $row = $daily->get($slotId);
                if (! $row || $row->is_blocked) {
                    continue;
                }

                // Ako postoji pending > 0: worklist (po temp_data) i ne blokiraj odmah.
                if ($row->pending > 0) {
                    $temps = TempData::query()
                        ->where('status', TempData::STATUS_PENDING)
                        ->where('reservation_date', $date)
                        ->where(function ($q) use ($slotId) {
                            $q->where('drop_off_time_slot_id', $slotId)->orWhere('pick_up_time_slot_id', $slotId);
                        })
                        ->get();
                    foreach ($temps as $temp) {
                        $this->upsertWorklistForTemp($temp, $slotId);
                    }
                    continue;
                }

                // Ako postoji reserved > 0: worklist (po reservation) i ne blokiraj odmah.
                if ($row->reserved > 0) {
                    $reservations = Reservation::query()
                        ->where('reservation_date', $date)
                        ->where(function ($q) use ($slotId) {
                            $q->where('drop_off_time_slot_id', $slotId)->orWhere('pick_up_time_slot_id', $slotId);
                        })
                        ->get();
                    foreach ($reservations as $r) {
                        $this->upsertWorklistForReservation($r, $slotId);
                    }
                    continue;
                }

                // Slobodno: blokiraj odmah.
                $row->is_blocked = true;
                $row->save();
            }
        });
    }

    /**
     * @param  list<int>  $slotIdsToUnblock
     */
    public function applyUnblock(string $date, array $slotIdsToUnblock): void
    {
        $slotIdsToUnblock = array_values(array_unique(array_map('intval', $slotIdsToUnblock)));
        $slotIdsToUnblock = array_values(array_filter($slotIdsToUnblock, fn (int $id) => $id > 0));

        DB::transaction(function () use ($date, $slotIdsToUnblock): void {
            if ($slotIdsToUnblock !== []) {
                DailyParkingData::query()
                    ->where('date', $date)
                    ->whereIn('time_slot_id', $slotIdsToUnblock)
                    ->lockForUpdate()
                    ->update(['is_blocked' => false]);
            }

            // Očisti worklist stavke koje više nisu u blok zoni.
            $work = BlockZoneWorklist::query()
                ->whereDate('old_date', $date)
                ->get();
            if ($work->isEmpty()) {
                return;
            }

            $blockedBySlot = DailyParkingData::query()
                ->where('date', $date)
                ->where('is_blocked', true)
                ->get()
                ->keyBy('time_slot_id');

            foreach ($work as $row) {
                $affectedDrop = (bool) $row->affected_drop_off;
                $affectedPick = (bool) $row->affected_pick_up;
                if ($affectedDrop && ! $blockedBySlot->has((int) $row->old_drop_off)) {
                    $affectedDrop = false;
                }
                if ($affectedPick && ! $blockedBySlot->has((int) $row->old_pick_up)) {
                    $affectedPick = false;
                }

                if (! $affectedDrop && ! $affectedPick) {
                    $row->delete();
                    continue;
                }

                if ($affectedDrop !== (bool) $row->affected_drop_off || $affectedPick !== (bool) $row->affected_pick_up) {
                    $row->affected_drop_off = $affectedDrop;
                    $row->affected_pick_up = $affectedPick;
                    $row->save();
                }
            }
        });
    }

    public function upsertWorklistForReservation(Reservation $r, int $affectedSlotId): void
    {
        $drop = (int) $r->drop_off_time_slot_id;
        $pick = (int) $r->pick_up_time_slot_id;

        $existing = BlockZoneWorklist::query()
            ->where('merchant_transaction_id', $r->merchant_transaction_id)
            ->first();

        $payload = [
            'user_name' => $r->user_name,
            'email' => $r->email,
            'reservation_id' => $r->id,
            'reservation_status' => $r->status,
            'target_block_slots' => array_values(array_unique(array_merge(
                (array) (($existing?->snapshot_json['target_block_slots'] ?? []) ?: []),
                [$affectedSlotId]
            ))),
        ];

        BlockZoneWorklist::query()->updateOrCreate(
            ['merchant_transaction_id' => $r->merchant_transaction_id],
            [
                'status' => BlockZoneWorklist::STATUS_READY_TO_ADJUST,
                'old_date' => $r->reservation_date,
                'old_drop_off' => $drop,
                'old_pick_up' => $pick,
                'affected_drop_off' => $drop === $affectedSlotId ? true : ($existing?->affected_drop_off ?? false),
                'affected_pick_up' => $pick === $affectedSlotId ? true : ($existing?->affected_pick_up ?? false),
                'snapshot_json' => $payload,
                'reservation_id' => $r->id,
                'temp_data_id' => null,
            ],
        );
    }

    public function upsertWorklistForTemp(TempData $temp, int $affectedSlotId): void
    {
        $drop = (int) $temp->drop_off_time_slot_id;
        $pick = (int) $temp->pick_up_time_slot_id;

        $existing = BlockZoneWorklist::query()
            ->where('merchant_transaction_id', $temp->merchant_transaction_id)
            ->first();

        $payload = [
            'user_name' => $temp->user_name,
            'email' => $temp->email,
            'temp_data_id' => $temp->id,
            'target_block_slots' => array_values(array_unique(array_merge(
                (array) (($existing?->snapshot_json['target_block_slots'] ?? []) ?: []),
                [$affectedSlotId]
            ))),
        ];

        BlockZoneWorklist::query()->updateOrCreate(
            ['merchant_transaction_id' => $temp->merchant_transaction_id],
            [
                'status' => BlockZoneWorklist::STATUS_PENDING_PAYMENT,
                'old_date' => $temp->reservation_date,
                'old_drop_off' => $drop,
                'old_pick_up' => $pick,
                'affected_drop_off' => $drop === $affectedSlotId ? true : ($existing?->affected_drop_off ?? false),
                'affected_pick_up' => $pick === $affectedSlotId ? true : ($existing?->affected_pick_up ?? false),
                'snapshot_json' => $payload,
                'reservation_id' => null,
                'temp_data_id' => $temp->id,
            ],
        );
    }

    /**
     * @return array<int, array{date:string, is_full_day:bool, ranges:list<string>, slot_ids:list<int>}>
     */
    public function blockedDaySummaries(int $daysAhead = 90): array
    {
        $from = now()->toDateString();
        $to = now()->addDays($daysAhead)->toDateString();

        $allSlots = $this->allSlots()->keyBy('id');
        $slotCount = $allSlots->count();

        $blocked = DailyParkingData::query()
            ->whereBetween('date', [$from, $to])
            ->where('is_blocked', true)
            ->orderBy('date')
            ->orderBy('time_slot_id')
            ->get()
            ->groupBy(fn (DailyParkingData $d) => $d->date->toDateString());

        $out = [];
        foreach ($blocked as $date => $rows) {
            $slotIds = $rows->pluck('time_slot_id')->map(fn ($v) => (int) $v)->values()->all();
            $isFull = $slotCount > 0 && count($slotIds) >= $slotCount;
            $ranges = $isFull ? [] : $this->mergeConsecutiveSlotRanges($slotIds, $allSlots);
            $out[] = [
                'date' => $date,
                'is_full_day' => $isFull,
                'ranges' => $ranges,
                'slot_ids' => $slotIds,
            ];
        }

        return $out;
    }

    /**
     * Prefilter datuma za UI prilagođavanja (nije garancija). Dan ulazi ako postoje najmanje 2 termina
     * sa `availableCapacity() >= 1`, neblokirani i bez pending-a (teorijski prostor za drop+pick).
     *
     * @return list<string> Y-m-d
     */
    public function datesEligibleForAdjustmentPrefilter(int $daysAhead = 90): array
    {
        $from = now()->startOfDay();
        $to = $from->copy()->addDays($daysAhead);

        if ($this->allSlots()->count() < 2) {
            return [];
        }

        $eligible = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $dateStr = $cursor->toDateString();
            $rows = DailyParkingData::query()
                ->whereDate('date', $dateStr)
                ->get();
            $withRoom = $rows->filter(function (DailyParkingData $d): bool {
                return ! $d->is_blocked
                    && (int) $d->pending === 0
                    && $d->availableCapacity() >= 1;
            });
            if ($withRoom->count() >= 2) {
                $eligible[] = $dateStr;
            }
            $cursor->addDay();
        }

        return $eligible;
    }

    /**
     * @param  list<int>  $slotIds
     * @param  Collection<int, ListOfTimeSlot>  $slotsById
     * @return list<string>
     */
    private function mergeConsecutiveSlotRanges(array $slotIds, Collection $slotsById): array
    {
        sort($slotIds);
        $ranges = [];
        $start = null;
        $prev = null;
        foreach ($slotIds as $id) {
            if ($start === null) {
                $start = $id;
                $prev = $id;
                continue;
            }
            if ($id === $prev + 1) {
                $prev = $id;
                continue;
            }
            $ranges[] = $this->formatRange($start, $prev, $slotsById);
            $start = $id;
            $prev = $id;
        }
        if ($start !== null && $prev !== null) {
            $ranges[] = $this->formatRange($start, $prev, $slotsById);
        }

        return $ranges;
    }

    private function formatRange(int $startId, int $endId, Collection $slotsById): string
    {
        $start = $slotsById->get($startId)?->time_slot ?? ('#'.$startId);
        $end = $slotsById->get($endId)?->time_slot ?? ('#'.$endId);
        if ($startId === $endId) {
            return $start;
        }

        return $start.' … '.$end;
    }
}

