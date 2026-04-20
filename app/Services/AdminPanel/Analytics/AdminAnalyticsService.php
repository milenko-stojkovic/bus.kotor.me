<?php

namespace App\Services\AdminPanel\Analytics;

use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\PostFiscalizationData;
use App\Models\Reservation;
use App\Models\TempData;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AdminAnalyticsService
{
    /**
     * @return array<string, mixed> Aggregate dataset (dashboard + PDF).
     */
    public function build(string $dateFrom, string $dateTo, bool $includeFree): array
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->startOfDay();

        $slots = ListOfTimeSlot::query()->orderBy('id')->get();
        $slotById = $slots->keyBy('id');
        $slotCount = $slots->count();
        $dayCount = (int) $from->diffInDays($to) + 1;

        $reservations = Reservation::query()
            ->with(['vehicleType.translations', 'dropOffTimeSlot', 'pickUpTimeSlot'])
            // Use whereDate to be safe across SQLite/MySQL date storage differences.
            ->whereDate('reservation_date', '>=', $from->toDateString())
            ->whereDate('reservation_date', '<=', $to->toDateString())
            ->when(! $includeFree, fn ($q) => $q->where('status', 'paid'))
            ->get();

        $paid = $reservations->where('status', 'paid');
        $free = $reservations->where('status', 'free');

        $revenueTotal = (float) $paid->sum(fn (Reservation $r) => (float) ($r->invoice_amount ?? 0));
        $paidCount = $paid->count();
        $freeCount = $free->count();
        $reservationCount = $reservations->count();

        $occupiedSlotsTotal = $this->occupiedSlotsFor($reservations);
        $occupiedSlotsPaid = $this->occupiedSlotsFor($paid);
        $occupiedSlotsFree = $this->occupiedSlotsFor($free);

        $maxSlotOccurrences = max(1, $slotCount * $dayCount);
        $avgOccupancySlotLevel = $occupiedSlotsTotal / $maxSlotOccurrences;

        // Blocking / capacity loss metrics from daily_parking_data (source of truth).
        $daily = DailyParkingData::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get();
        $blockedRows = $daily->where('is_blocked', true);
        $blockedSlotRowsCount = $blockedRows->count();
        $blockedCapacity = (int) $blockedRows->sum('capacity');
        $totalCapacity = (int) $daily->sum('capacity');
        $blockedCapacityPct = $totalCapacity > 0 ? ($blockedCapacity / $totalCapacity) : 0.0;

        // Fully blocked day = all slots exist and are blocked.
        $blockedByDate = $blockedRows->groupBy(fn (DailyParkingData $d) => $d->date->toDateString());
        $fullyBlockedDays = 0;
        foreach ($blockedByDate as $dateStr => $rows) {
            $blockedSlotIds = $rows->pluck('time_slot_id')->unique()->count();
            if ($slotCount > 0 && $blockedSlotIds >= $slotCount) {
                $fullyBlockedDays++;
            }
        }

        $blockingTopDays = $blockedByDate
            ->map(function (Collection $rows, string $dateStr) use ($slotCount): array {
                $cnt = $rows->pluck('time_slot_id')->unique()->count();

                return [
                    'date' => $dateStr,
                    'blocked_slots' => $cnt,
                    'blocked_slots_pct' => $slotCount > 0 ? ($cnt / $slotCount) : 0.0,
                ];
            })
            ->sortByDesc('blocked_slots')
            ->take(10)
            ->values()
            ->all();

        // Trend by day.
        $trendByDay = $this->trendByDay($from, $to, $reservations);

        // Day parts.
        $dayParts = $this->groupByDayPart($reservations, $slotById);

        // Vehicle types.
        $byVehicleType = $this->groupByVehicleType($reservations);

        // Countries.
        $byCountry = $this->groupByCountry($reservations);

        // Paid vs free section (always present; free values may be 0 if includeFree=false).
        $paidVsFree = [
            'paid_reservations' => $paidCount,
            'free_reservations' => $freeCount,
            'paid_occupied_slots' => $occupiedSlotsPaid,
            'free_occupied_slots' => $occupiedSlotsFree,
            'paid_revenue' => $revenueTotal,
            'free_capacity_pct_by_slots' => $occupiedSlotsTotal > 0 ? ($occupiedSlotsFree / $occupiedSlotsTotal) : 0.0,
        ];

        // Operational problems / recovery (from existing payment state machine tables).
        $ops = $this->operationalProblems($from, $to);

        $kpi = [
            'revenue_total' => $revenueTotal,
            'reservations_total' => $reservationCount,
            'paid_reservations' => $paidCount,
            'free_reservations' => $freeCount,
            'avg_revenue_per_paid' => $paidCount > 0 ? ($revenueTotal / $paidCount) : 0.0,
            'occupied_slots_total' => $occupiedSlotsTotal,
            'avg_occupancy_slot_level' => $avgOccupancySlotLevel,
            'blocked_slot_rows' => $blockedSlotRowsCount,
            'fully_blocked_days' => $fullyBlockedDays,
            'blocked_capacity_pct' => $blockedCapacityPct,
        ];

        return [
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'include_free' => $includeFree,
                'day_count' => $dayCount,
            ],
            'kpi' => $kpi,
            'trend_by_day' => $trendByDay,
            'day_parts' => $dayParts,
            'by_vehicle_type' => $byVehicleType,
            'by_country' => $byCountry,
            'paid_vs_free' => $paidVsFree,
            'blocking' => [
                'blocked_slot_rows' => $blockedSlotRowsCount,
                'fully_blocked_days' => $fullyBlockedDays,
                'blocked_capacity_pct' => $blockedCapacityPct,
                'top_days' => $blockingTopDays,
            ],
            'ops' => $ops,
        ];
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     */
    private function occupiedSlotsFor(Collection $reservations): int
    {
        $total = 0;
        foreach ($reservations as $r) {
            $drop = (int) $r->drop_off_time_slot_id;
            $pick = (int) $r->pick_up_time_slot_id;
            $total += ($drop === $pick) ? 1 : 2;
        }

        return $total;
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @param  Collection<int, ListOfTimeSlot>  $slotById
     * @return list<array{key:string,label:string,reservations:int,occupied_slots:int,revenue:float,share_occupied:float,share_revenue:float}>
     */
    private function groupByDayPart(Collection $reservations, Collection $slotById): array
    {
        $groups = [
            AdminAnalyticsDefinitions::PART_FREE_MORNING => ['reservations' => 0, 'occupied' => 0, 'revenue' => 0.0],
            AdminAnalyticsDefinitions::PART_PAID_DAY => ['reservations' => 0, 'occupied' => 0, 'revenue' => 0.0],
            AdminAnalyticsDefinitions::PART_FREE_EVENING => ['reservations' => 0, 'occupied' => 0, 'revenue' => 0.0],
        ];

        $totalOcc = $this->occupiedSlotsFor($reservations);
        $totalRev = (float) $reservations->where('status', 'paid')->sum(fn (Reservation $r) => (float) ($r->invoice_amount ?? 0));

        foreach ($reservations as $r) {
            $dropSlot = $slotById->get((int) $r->drop_off_time_slot_id);
            $part = AdminAnalyticsDefinitions::dayPartForSlot($dropSlot);
            $groups[$part]['reservations']++;
            $groups[$part]['occupied'] += ((int) $r->drop_off_time_slot_id === (int) $r->pick_up_time_slot_id) ? 1 : 2;
            if ($r->status === 'paid') {
                $groups[$part]['revenue'] += (float) ($r->invoice_amount ?? 0);
            }
        }

        $out = [];
        foreach ($groups as $key => $g) {
            $out[] = [
                'key' => $key,
                'label' => AdminAnalyticsDefinitions::dayPartLabel($key),
                'reservations' => (int) $g['reservations'],
                'occupied_slots' => (int) $g['occupied'],
                'revenue' => (float) $g['revenue'],
                'share_occupied' => $totalOcc > 0 ? ((int) $g['occupied'] / $totalOcc) : 0.0,
                'share_revenue' => $totalRev > 0 ? ((float) $g['revenue'] / $totalRev) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return list<array{vehicle_type_id:int,name:string,reservations:int,occupied_slots:int,revenue:float,avg_revenue:float}>
     */
    private function groupByVehicleType(Collection $reservations): array
    {
        $locale = 'cg';

        $groups = $reservations->groupBy('vehicle_type_id')->map(function (Collection $rows, $vtId) use ($locale): array {
            $paidRows = $rows->where('status', 'paid');
            $rev = (float) $paidRows->sum(fn (Reservation $r) => (float) ($r->invoice_amount ?? 0));
            $cnt = $rows->count();
            $occ = $this->occupiedSlotsFor($rows);

            /** @var Reservation|null $first */
            $first = $rows->first();
            $name = $first?->vehicleType?->getTranslatedName($locale) ?: ('#'.$vtId);

            return [
                'vehicle_type_id' => (int) $vtId,
                'name' => $name,
                'reservations' => $cnt,
                'occupied_slots' => $occ,
                'revenue' => $rev,
                'avg_revenue' => $cnt > 0 ? ($rev / $cnt) : 0.0,
            ];
        });

        return $groups->sortByDesc('revenue')->values()->all();
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return list<array{country:string,reservations:int,paid:int,free:int,revenue:float}>
     */
    private function groupByCountry(Collection $reservations): array
    {
        $groups = $reservations->groupBy('country')->map(function (Collection $rows, $cc): array {
            $paidRows = $rows->where('status', 'paid');
            $freeRows = $rows->where('status', 'free');
            $rev = (float) $paidRows->sum(fn (Reservation $r) => (float) ($r->invoice_amount ?? 0));

            return [
                'country' => (string) $cc,
                'reservations' => $rows->count(),
                'paid' => $paidRows->count(),
                'free' => $freeRows->count(),
                'revenue' => $rev,
            ];
        });

        return $groups->sortByDesc('revenue')->values()->all();
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return list<array{date:string,reservations:int,paid:int,free:int,revenue:float,occupied_slots:int}>
     */
    private function trendByDay(Carbon $from, Carbon $to, Collection $reservations): array
    {
        $map = $reservations->groupBy(fn (Reservation $r) => $r->reservation_date->toDateString());

        $out = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $d = $cursor->toDateString();
            $rows = $map->get($d, collect());
            $paidRows = $rows->where('status', 'paid');
            $freeRows = $rows->where('status', 'free');
            $out[] = [
                'date' => $d,
                'reservations' => $rows->count(),
                'paid' => $paidRows->count(),
                'free' => $freeRows->count(),
                'revenue' => (float) $paidRows->sum(fn (Reservation $r) => (float) ($r->invoice_amount ?? 0)),
                'occupied_slots' => $this->occupiedSlotsFor($rows),
            ];
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * Operational problems / recovery overview (no new logic; uses existing state machine tables).
     *
     * @return array<string,int>
     */
    private function operationalProblems(Carbon $from, Carbon $to): array
    {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        // Payment attempts (temp_data) keyed by reservation_date in selected interval.
        $failed = TempData::query()
            ->whereDate('reservation_date', '>=', $fromStr)
            ->whereDate('reservation_date', '<=', $toStr)
            ->whereIn('status', array_values(array_unique([
                // Older SQLite test schema uses 'failed' enum value.
                'failed',
                // Newer schemas may have canceled as separate terminal state.
                TempData::STATUS_CANCELED,
            ])))
            ->count();
        $expired = TempData::query()
            ->whereDate('reservation_date', '>=', $fromStr)
            ->whereDate('reservation_date', '<=', $toStr)
            ->where('status', TempData::STATUS_EXPIRED)
            ->count();
        $lateSuccess = TempData::query()
            ->whereDate('reservation_date', '>=', $fromStr)
            ->whereDate('reservation_date', '<=', $toStr)
            ->where('status', TempData::STATUS_LATE_SUCCESS)
            ->count();

        // Delayed fiscalization: paid reservations without fiscal_jir OR unresolved post_fiscal rows.
        $paidNoFiscal = Reservation::query()
            ->whereDate('reservation_date', '>=', $fromStr)
            ->whereDate('reservation_date', '<=', $toStr)
            ->where('status', 'paid')
            ->whereNull('fiscal_jir')
            ->count();

        $unresolvedPostFiscal = PostFiscalizationData::query()
            ->unresolved()
            ->whereHas('reservation', function ($q) use ($fromStr, $toStr): void {
                $q->whereDate('reservation_date', '>=', $fromStr)->whereDate('reservation_date', '<=', $toStr);
            })
            ->count();

        // Successful post-fiscal recovery: resolved_at within interval (best effort).
        $resolvedPostFiscal = PostFiscalizationData::query()
            ->whereNotNull('resolved_at')
            ->whereBetween(DB::raw('DATE(resolved_at)'), [$fromStr, $toStr])
            ->count();

        return [
            'failed_payments' => $failed,
            'expired_payments' => $expired,
            'late_success' => $lateSuccess,
            'paid_without_fiscal_jir' => $paidNoFiscal,
            'unresolved_post_fiscal' => $unresolvedPostFiscal,
            'resolved_post_fiscal' => $resolvedPostFiscal,
        ];
    }
}

