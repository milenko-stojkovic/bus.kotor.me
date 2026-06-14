<?php

namespace App\Services\Panel;

use App\Models\FreeReservationRequest;
use App\Models\FreeReservationRequestSegment;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Reservation\PanelReservationListService;
use App\Support\UiText;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class AgencyFzbrSubmittedRequestListService
{
    /**
     * @return Collection<int, FreeReservationRequest>
     */
    public function visibleForAgency(User $user): Collection
    {
        return FreeReservationRequest::query()
            ->where('user_id', $user->id)
            ->with([
                'segments.dropOffTimeSlot',
                'segments.pickUpTimeSlot',
                'segments.vehicles',
                'vehicles',
                'reservations.dropOffTimeSlot',
                'reservations.pickUpTimeSlot',
                'dropOffTimeSlot',
                'pickUpTimeSlot',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (FreeReservationRequest $req) => $this->isVisible($req))
            ->values();
    }

    /**
     * Rows rendered in the agency submitted-requests table.
     *
     * @return Collection<int, array{date: string, arrival: string, departure: string, plates: string}>
     */
    public function listEntriesForRequest(FreeReservationRequest $req): Collection
    {
        if ($req->status !== FreeReservationRequest::STATUS_FULFILLED) {
            return $this->requestSnapshotEntries($req);
        }

        $upcomingLinked = $this->upcomingLinkedReservations($req);
        if ($upcomingLinked->isNotEmpty()) {
            return $upcomingLinked->map(fn (Reservation $r): array => [
                'date' => $r->reservation_date?->format('Y-m-d') ?? '—',
                'arrival' => $r->dropOffTimeSlot?->time_slot ?? '—',
                'departure' => $r->pickUpTimeSlot?->time_slot ?? '—',
                'plates' => trim((string) $r->license_plate) !== '' ? (string) $r->license_plate : '—',
            ])->values();
        }

        return $this->upcomingRequestSnapshotEntries($req);
    }

    /**
     * Linked free reservations that are still upcoming (fulfilled requests only).
     *
     * @return Collection<int, Reservation>
     */
    public function upcomingLinkedReservations(FreeReservationRequest $req): Collection
    {
        if ($req->status !== FreeReservationRequest::STATUS_FULFILLED) {
            return collect();
        }

        return $this->linkedReservations($req)
            ->filter(fn (Reservation $r) => PanelReservationListService::isUpcoming($r))
            ->values();
    }

    public function isVisible(FreeReservationRequest $req): bool
    {
        if (in_array($req->status, [
            FreeReservationRequest::STATUS_SUBMITTED,
            FreeReservationRequest::STATUS_UPDATED,
        ], true)) {
            return true;
        }

        if ($req->status === FreeReservationRequest::STATUS_REJECTED) {
            return $this->requestSnapshotHasUpcomingSegment($req);
        }

        if ($req->status !== FreeReservationRequest::STATUS_FULFILLED) {
            return false;
        }

        $linked = $this->linkedReservations($req);

        if ($linked->isNotEmpty()) {
            return $this->upcomingLinkedReservations($req)->isNotEmpty();
        }

        return $this->requestSnapshotHasUpcomingSegment($req);
    }

    /**
     * @return Collection<int, array{date: string, arrival: string, departure: string, plates: string}>
     */
    private function requestSnapshotEntries(FreeReservationRequest $req): Collection
    {
        $req->loadMissing(['segments.dropOffTimeSlot', 'segments.pickUpTimeSlot', 'segments.vehicles', 'dropOffTimeSlot', 'pickUpTimeSlot', 'vehicles']);

        if ($req->segments->isNotEmpty()) {
            return $req->segments->map(function (FreeReservationRequestSegment $seg) use ($req): array {
                $plates = $seg->vehicles
                    ->pluck('license_plate')
                    ->filter(fn ($plate) => is_string($plate) && trim($plate) !== '')
                    ->unique()
                    ->values();

                return [
                    'date' => ($seg->reservation_date ?? $req->reservation_date)?->format('Y-m-d') ?? '—',
                    'arrival' => $seg->dropOffTimeSlot?->time_slot ?? '—',
                    'departure' => $seg->pickUpTimeSlot?->time_slot ?? '—',
                    'plates' => $plates->isNotEmpty() ? $plates->join(', ') : '—',
                ];
            })->values();
        }

        $plates = $req->vehicles
            ->pluck('license_plate')
            ->filter(fn ($plate) => is_string($plate) && trim($plate) !== '')
            ->unique()
            ->values();

        return collect([[
            'date' => $req->reservation_date?->format('Y-m-d') ?? '—',
            'arrival' => $req->dropOffTimeSlot?->time_slot ?? '—',
            'departure' => $req->pickUpTimeSlot?->time_slot ?? '—',
            'plates' => $plates->isNotEmpty() ? $plates->join(', ') : '—',
        ]]);
    }

    /**
     * @return Collection<int, array{date: string, arrival: string, departure: string, plates: string}>
     */
    private function upcomingRequestSnapshotEntries(FreeReservationRequest $req): Collection
    {
        return $this->requestSnapshotEntries($req)
            ->filter(function (array $entry) use ($req): bool {
                $date = $entry['date'] !== '—' ? $entry['date'] : ($req->reservation_date?->format('Y-m-d') ?? '');
                $pickSlot = $this->pickUpSlotForSnapshotEntry($req, $entry);

                return $this->segmentIsUpcoming($date, $pickSlot);
            })
            ->values();
    }

    private function requestSnapshotHasUpcomingSegment(FreeReservationRequest $req): bool
    {
        return $this->upcomingRequestSnapshotEntries($req)->isNotEmpty();
    }

    /**
     * @return Collection<int, Reservation>
     */
    private function linkedReservations(FreeReservationRequest $req): Collection
    {
        $fkLinked = $this->fkLinkedReservations($req);

        if ($fkLinked->isNotEmpty() || $req->status !== FreeReservationRequest::STATUS_FULFILLED) {
            return $fkLinked;
        }

        $discovered = $this->discoverOrphanLinkedReservations($req);
        if ($discovered->isNotEmpty()) {
            $this->backfillRequestLink($req, $discovered);
        }

        return $discovered;
    }

    /**
     * @return Collection<int, Reservation>
     */
    private function fkLinkedReservations(FreeReservationRequest $req): Collection
    {
        if ($req->relationLoaded('reservations')) {
            return $req->reservations;
        }

        return Reservation::query()
            ->where('free_reservation_request_id', $req->id)
            ->with(['pickUpTimeSlot', 'dropOffTimeSlot'])
            ->get();
    }

    /**
     * Fulfilled requests approved before FK existed: match admin-free rows by request snapshot fields.
     *
     * @return Collection<int, Reservation>
     */
    private function discoverOrphanLinkedReservations(FreeReservationRequest $req): Collection
    {
        $req->loadMissing(['vehicles', 'segments']);

        $email = trim((string) $req->institution_email);
        if ($email === '') {
            return collect();
        }

        $plates = $req->vehicles
            ->pluck('license_plate')
            ->filter(fn ($plate) => is_string($plate) && trim($plate) !== '')
            ->unique()
            ->values()
            ->all();

        if ($plates === []) {
            return collect();
        }

        $query = Reservation::query()
            ->whereNull('free_reservation_request_id')
            ->where('status', 'free')
            ->where('created_by_admin', true)
            ->where('email', $email)
            ->whereIn('license_plate', $plates)
            ->with(['pickUpTimeSlot', 'dropOffTimeSlot']);

        if ($req->segments->isNotEmpty()) {
            $query->where(function ($outer) use ($req): void {
                foreach ($req->segments as $seg) {
                    $date = $seg->reservation_date?->toDateString() ?? $req->reservation_date?->toDateString();
                    if ($date === null || $date === '') {
                        continue;
                    }

                    $outer->orWhere(function ($q) use ($seg, $date): void {
                        $q->whereDate('reservation_date', $date)
                            ->where('drop_off_time_slot_id', (int) $seg->drop_off_time_slot_id)
                            ->where('pick_up_time_slot_id', (int) $seg->pick_up_time_slot_id);
                    });
                }
            });
        } else {
            $date = $req->reservation_date?->toDateString();
            if ($date === null || $date === '') {
                return collect();
            }

            $query->whereDate('reservation_date', $date)
                ->where('drop_off_time_slot_id', (int) $req->drop_off_time_slot_id)
                ->where('pick_up_time_slot_id', (int) $req->pick_up_time_slot_id);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Reservation>  $discovered
     */
    private function backfillRequestLink(FreeReservationRequest $req, Collection $discovered): void
    {
        $ids = $discovered->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        Reservation::query()
            ->whereIn('id', $ids)
            ->whereNull('free_reservation_request_id')
            ->update(['free_reservation_request_id' => $req->id]);

        $discovered->each(function (Reservation $r) use ($req): void {
            $r->free_reservation_request_id = $req->id;
        });

        if ($req->relationLoaded('reservations')) {
            $req->setRelation('reservations', $discovered);
        }
    }

    private function segmentIsUpcoming(string $date, ?ListOfTimeSlot $pickSlot): bool
    {
        if ($date === '' || $pickSlot === null) {
            return false;
        }

        $day = Carbon::parse($date)->timezone(PanelReservationListService::OPERATIONS_TIMEZONE)->startOfDay();
        $today = Carbon::now(PanelReservationListService::OPERATIONS_TIMEZONE)->startOfDay();

        if ($day->gt($today)) {
            return true;
        }
        if ($day->lt($today)) {
            return false;
        }

        $end = $pickSlot->getEndTimeForDate($day);
        if ($end === null) {
            return false;
        }

        return now(PanelReservationListService::OPERATIONS_TIMEZONE)->lt($end);
    }

    /**
     * @param  array{date: string, arrival: string, departure: string, plates: string}  $entry
     */
    private function pickUpSlotForSnapshotEntry(FreeReservationRequest $req, array $entry): ?ListOfTimeSlot
    {
        if ($req->segments->isNotEmpty()) {
            foreach ($req->segments as $seg) {
                $date = ($seg->reservation_date ?? $req->reservation_date)?->format('Y-m-d') ?? '—';
                $departure = $seg->pickUpTimeSlot?->time_slot ?? '—';
                if ($date === $entry['date'] && $departure === $entry['departure']) {
                    return $seg->pickUpTimeSlot;
                }
            }
        }

        return $req->pickUpTimeSlot;
    }

    public function statusLabel(string $status, string $locale): string
    {
        $key = match ($status) {
            FreeReservationRequest::STATUS_SUBMITTED,
            FreeReservationRequest::STATUS_UPDATED => 'fzbr_request_status_pending',
            FreeReservationRequest::STATUS_FULFILLED => 'fzbr_request_status_approved',
            FreeReservationRequest::STATUS_REJECTED => 'fzbr_request_status_rejected',
            default => null,
        };

        if ($key === null) {
            return $status;
        }

        return UiText::t('panel', $key, $this->statusFallback($key, $locale), $locale);
    }

    private function statusFallback(string $key, string $locale): string
    {
        $cg = $locale === 'cg';

        return match ($key) {
            'fzbr_request_status_pending' => $cg ? 'Čeka se obrada' : 'Awaiting processing',
            'fzbr_request_status_approved' => $cg ? 'Odobreno' : 'Approved',
            'fzbr_request_status_rejected' => $cg ? 'Odbijeno' : 'Rejected',
            default => $key,
        };
    }
}
