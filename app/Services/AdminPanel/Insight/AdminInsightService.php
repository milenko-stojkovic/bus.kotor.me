<?php

namespace App\Services\AdminPanel\Insight;

use App\Models\Reservation;
use App\Models\TempData;
use App\Services\AdminPanel\Reservation\AdminReservationSearchHeuristic;
use Illuminate\Support\Collection;

final class AdminInsightService
{
    public function __construct(
        private readonly AdminReservationSearchHeuristic $heuristic,
        private readonly PaymentLogTimelineService $timeline,
    ) {}

    /**
     * Search primary source-of-truth: temp_data.
     *
     * @param  array<string, mixed>  $criteria
     * @return array{results:list<array<string,mixed>>,admin_free_reservation?:array<string,mixed>}
     */
    public function search(array $criteria): array
    {
        $q = TempData::query()
            ->with(['dropOffTimeSlot', 'pickUpTimeSlot', 'vehicleType.translations'])
            ->orderByDesc('created_at');

        if (! empty($criteria['merchant_transaction_id'])) {
            $q->where('merchant_transaction_id', 'like', '%'.trim((string) $criteria['merchant_transaction_id']).'%');
        }
        if (! empty($criteria['date_from'])) {
            $q->whereDate('created_at', '>=', (string) $criteria['date_from']);
        }
        if (! empty($criteria['date_to'])) {
            $q->whereDate('created_at', '<=', (string) $criteria['date_to']);
        }
        if (! empty($criteria['user_name'])) {
            $patterns = $this->heuristic->nameLikePatterns((string) $criteria['user_name']);
            $q->where(function ($qq) use ($patterns): void {
                foreach ($patterns as $p) {
                    $qq->orWhere('user_name', 'like', $p);
                }
            });
        }
        if (! empty($criteria['email'])) {
            $patterns = $this->heuristic->emailLikePatterns((string) $criteria['email']);
            $q->where(function ($qq) use ($patterns): void {
                foreach ($patterns as $p) {
                    $qq->orWhere('email', 'like', $p);
                }
            });
        }
        if (! empty($criteria['vehicle_type_id'])) {
            $q->where('vehicle_type_id', (int) $criteria['vehicle_type_id']);
        }
        if (! empty($criteria['license_plate'])) {
            $plate = trim((string) $criteria['license_plate']);
            $q->where('license_plate', 'like', '%'.$plate.'%');
        }
        if (! empty($criteria['country'])) {
            $q->where('country', strtoupper(trim((string) $criteria['country'])));
        }
        if (! empty($criteria['status'])) {
            $q->where('status', (string) $criteria['status']);
        }
        if (! empty($criteria['resolution_reason'])) {
            $q->where('resolution_reason', 'like', '%'.trim((string) $criteria['resolution_reason']).'%');
        }

        /** @var Collection<int, TempData> $rows */
        $rows = $q->limit(200)->get();
        $mtids = $rows->pluck('merchant_transaction_id')->filter()->unique()->values();

        $reservationsByMtid = Reservation::query()
            ->whereIn('merchant_transaction_id', $mtids->all())
            ->get()
            ->keyBy('merchant_transaction_id');

        $out = [];
        foreach ($rows as $t) {
            $r = $reservationsByMtid->get((string) $t->merchant_transaction_id);
            $out[] = [
                'merchant_transaction_id' => (string) $t->merchant_transaction_id,
                'created_at' => $t->created_at?->toDateTimeString(),
                'status' => (string) $t->status,
                'resolution_reason' => (string) ($t->resolution_reason ?? ''),
                'user_name' => (string) ($t->user_name ?? ''),
                'email' => (string) ($t->email ?? ''),
                'reservation_date' => $t->reservation_date?->toDateString(),
                'country' => (string) ($t->country ?? ''),
                'license_plate' => (string) ($t->license_plate ?? ''),
                'drop_off' => (string) ($t->dropOffTimeSlot?->time_slot ?? ''),
                'pick_up' => (string) ($t->pickUpTimeSlot?->time_slot ?? ''),
                'vehicle_type' => $t->vehicleType?->formatLabel('cg') ?? '',
                'reservation_exists' => $r !== null,
                'reservation_status' => $r?->status,
                'reservation_is_admin_free' => (bool) ($r?->created_by_admin && $r?->status === 'free'),
            ];
        }

        // Special fallback: if MTID looks up an admin-free reservation (no temp_data case)
        $adminFree = null;
        $mtidExact = trim((string) ($criteria['merchant_transaction_id'] ?? ''));
        if ($mtidExact !== '' && $rows->isEmpty()) {
            $r = Reservation::query()->where('merchant_transaction_id', $mtidExact)->first();
            if ($r && $r->created_by_admin && $r->status === 'free') {
                $adminFree = [
                    'merchant_transaction_id' => $mtidExact,
                    'note' => 'Ovo je admin-free rezervacija (ne pripada payment lifecycle-u).',
                    'reservation_status' => $r->status,
                    'reservation_date' => $r->reservation_date?->toDateString(),
                ];
            }
        }

        return ['results' => $out, 'admin_free_reservation' => $adminFree];
    }

    /**
     * @return array<string, mixed>
     */
    public function case(string $merchantTransactionId): array
    {
        $mtid = trim($merchantTransactionId);

        $t = TempData::query()
            ->with(['dropOffTimeSlot', 'pickUpTimeSlot', 'vehicleType.translations'])
            ->where('merchant_transaction_id', $mtid)
            ->first();

        $r = Reservation::query()
            ->where('merchant_transaction_id', $mtid)
            ->first();

        if (! $t && $r && $r->created_by_admin && $r->status === 'free') {
            return [
                'merchant_transaction_id' => $mtid,
                'is_admin_free_reservation' => true,
                'temp' => null,
                'reservation' => $r,
                'timeline' => [],
                'timeline_available' => false,
            ];
        }

        if (! $t) {
            abort(404);
        }

        $timeline = $this->timeline->timelineForMtid($mtid);

        return [
            'merchant_transaction_id' => $mtid,
            'is_admin_free_reservation' => (bool) ($r?->created_by_admin && $r?->status === 'free'),
            'temp' => $t,
            'reservation' => $r,
            'timeline' => $timeline['events'],
            'timeline_available' => $timeline['available'],
            'timeline_note' => $timeline['note'],
        ];
    }
}

