<?php

namespace App\Services\Reservation;

use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Shared candidate engine for agency vehicle replacement.
 *
 * Conflict rule: same date AND (drop=drop OR pick=pick). Cross-match drop=pick / pick=drop is NOT a conflict.
 * Category rule: candidate vehicle type price must be <= maxPrice (same or lower category).
 */
final class VehicleReplacementCandidateService
{
    /**
     * @param  array{exclude_vehicle_id?: int, max_price?: float}  $opts
     * @return Collection<int, Vehicle>
     */
    public function candidatesForReservation(User $user, Reservation $reservation, array $opts = []): Collection
    {
        $excludeVehicleId = (int) ($opts['exclude_vehicle_id'] ?? 0);
        $maxPrice = $opts['max_price'] ?? null;

        $vehicles = $user->vehicles()
            ->where('status', Vehicle::STATUS_ACTIVE)
            ->with('vehicleType')
            ->orderBy('license_plate')
            ->get();

        $filtered = $vehicles
            ->filter(function (Vehicle $v) use ($excludeVehicleId, $maxPrice): bool {
                if ($excludeVehicleId > 0 && (int) $v->id === $excludeVehicleId) {
                    return false;
                }
                if ($maxPrice !== null) {
                    $p = (float) ($v->vehicleType?->price ?? 0);
                    if ($p > (float) $maxPrice + 0.000001) {
                        return false;
                    }
                }
                return true;
            })
            ->values();

        if ($filtered->isEmpty()) {
            return $filtered;
        }

        // Remove candidates that have a conflict with existing upcoming reservations.
        return $filtered->filter(function (Vehicle $candidate) use ($user, $reservation): bool {
            return ! $this->hasConflictWithUpcoming($user, $candidate->id, $reservation, ignoreReservationIds: []);
        })->values();
    }

    /**
     * @param  list<int>  $ignoreReservationIds Reservations that will be moved away from candidate (for combo checks).
     */
    public function hasConflictWithUpcoming(
        User $user,
        int $candidateVehicleId,
        Reservation $targetReservation,
        array $ignoreReservationIds,
    ): bool {
        $date = $targetReservation->reservation_date?->toDateString();
        if (! is_string($date) || $date === '') {
            return true;
        }

        $rows = Reservation::query()
            ->where('user_id', $user->id)
            ->where('vehicle_id', $candidateVehicleId)
            ->whereDate('reservation_date', $date)
            ->with(['pickUpTimeSlot'])
            ->get();

        foreach ($rows as $r) {
            if (! PanelReservationListService::isUpcoming($r)) {
                continue;
            }
            if (in_array((int) $r->id, $ignoreReservationIds, true)) {
                continue;
            }

            if ((int) $r->drop_off_time_slot_id === (int) $targetReservation->drop_off_time_slot_id) {
                return true;
            }
            if ((int) $r->pick_up_time_slot_id === (int) $targetReservation->pick_up_time_slot_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Final combination validation for multiple replacements at once.
     *
     * @param array<int,int> $replacementMap reservation_id => new_vehicle_id
     */
    public function validateReplacementCombination(User $user, array $replacementMap): bool
    {
        if ($replacementMap === []) {
            return false;
        }

        /** @var Collection<int, Reservation> $reservations */
        $reservations = Reservation::query()
            ->where('user_id', $user->id)
            ->whereIn('id', array_keys($replacementMap))
            ->with(['pickUpTimeSlot', 'dropOffTimeSlot', 'vehicleType'])
            ->get();

        if ($reservations->count() !== count($replacementMap)) {
            return false;
        }

        // Check pairwise conflicts among planned assignments for same candidate.
        $byVehicle = [];
        foreach ($reservations as $r) {
            $vid = (int) $replacementMap[(int) $r->id];
            $byVehicle[$vid] ??= [];
            $byVehicle[$vid][] = $r;
        }

        foreach ($byVehicle as $vid => $list) {
            $n = count($list);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = $list[$i];
                    $b = $list[$j];
                    if ($a->reservation_date?->toDateString() !== $b->reservation_date?->toDateString()) {
                        continue;
                    }
                    if ((int) $a->drop_off_time_slot_id === (int) $b->drop_off_time_slot_id) {
                        return false;
                    }
                    if ((int) $a->pick_up_time_slot_id === (int) $b->pick_up_time_slot_id) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
}

