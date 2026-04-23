<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\Reservation\PanelReservationListService;
use App\Services\Reservation\VehicleReplacementCandidateService;
use App\Support\UiText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(Request $request): View
    {
        $vehicles = $request->user()
            ->vehicles()
            ->with('vehicleType.translations')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $vehicleTypes = VehicleType::query()->with('translations')->orderBy('id')->get();

        return view('panel.vehicles', [
            'vehicles' => $vehicles,
            'vehicleTypes' => $vehicleTypes,
        ]);
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $request->user()->vehicles()->create($request->validated());

        $locale = $request->user()->lang ?? app()->getLocale();

        return redirect()->route('panel.vehicles')->with(
            'message',
            UiText::t('panel', 'vehicle_added', 'Vehicle added.', $locale)
        );
    }

    public function update(UpdateVehicleRequest $request, int $vehicle): RedirectResponse
    {
        $model = $this->ownedVehicleOrFail($request, $vehicle);
        $model->update($request->validated());

        $locale = $request->user()->lang ?? app()->getLocale();

        return redirect()->route('panel.vehicles')->with(
            'message',
            UiText::t('panel', 'vehicle_updated', 'Vehicle updated.', $locale)
        );
    }

    public function destroy(Request $request, int $vehicle, PanelReservationListService $lists): RedirectResponse
    {
        $model = $this->ownedVehicleOrFail($request, $vehicle);
        $locale = $request->user()->lang ?? app()->getLocale();

        $usedInUpcoming = $lists->upcomingFor($request->user())
            ->contains(fn ($r) => (int) ($r->vehicle_id ?? 0) === (int) $model->id);

        if ($usedInUpcoming) {
            // Start replacement workflow.
            return redirect()->route('panel.vehicles.remove', ['vehicle' => $model->id], false);
        }

        $model->delete();

        return redirect()->route('panel.vehicles')->with(
            'message',
            UiText::t('panel', 'vehicle_removed', 'Vehicle removed.', $locale)
        );
    }

    public function remove(
        Request $request,
        int $vehicle,
        PanelReservationListService $lists,
        VehicleReplacementCandidateService $candidates,
    ): View {
        $user = $request->user();
        $target = $this->ownedVehicleOrFail($request, $vehicle)->loadMissing('vehicleType');
        $locale = $user->lang ?? app()->getLocale();

        $upcoming = $lists->upcomingFor($user)->filter(fn ($r) => (int) ($r->vehicle_id ?? 0) === (int) $target->id)->values();
        $maxPrice = (float) ($target->vehicleType?->price ?? 0);

        $candidateMap = [];
        $missingAny = false;
        foreach ($upcoming as $r) {
            $candidateMap[(int) $r->id] = $candidates->candidatesForReservation($user, $r, [
                'exclude_vehicle_id' => (int) $target->id,
                'max_price' => $maxPrice,
            ]);
            if ($candidateMap[(int) $r->id]->isEmpty()) {
                $missingAny = true;
            }
        }

        return view('panel.vehicles-remove', [
            'targetVehicle' => $target,
            'upcomingReservations' => $upcoming,
            'candidateVehiclesByReservationId' => $candidateMap,
            'missingAnyCandidate' => $missingAny,
            'locale' => $locale,
        ]);
    }

    public function applyRemove(
        Request $request,
        int $vehicle,
        PanelReservationListService $lists,
        VehicleReplacementCandidateService $candidates,
    ): RedirectResponse {
        $user = $request->user();
        $target = $this->ownedVehicleOrFail($request, $vehicle)->loadMissing('vehicleType');
        $locale = $user->lang ?? app()->getLocale();

        $upcoming = $lists->upcomingFor($user)->filter(fn ($r) => (int) ($r->vehicle_id ?? 0) === (int) $target->id)->values();
        if ($upcoming->isEmpty()) {
            $target->delete();

            return redirect()->route('panel.vehicles')->with(
                'message',
                UiText::t('panel', 'vehicle_removed', 'Vehicle removed.', $locale)
            );
        }

        $data = $request->validate([
            'replacements' => ['required', 'array'],
            'replacements.*' => ['required', 'integer'],
        ]);

        /** @var array<int,int> $replacementMap */
        $replacementMap = [];
        foreach ($upcoming as $r) {
            $rid = (int) $r->id;
            $replacementMap[$rid] = (int) ($data['replacements'][$rid] ?? 0);
        }

        // Basic check: all reservation ids present and non-zero.
        foreach ($replacementMap as $rid => $vid) {
            if ($vid < 1) {
                return back()->with('error', UiText::t('panel', 'vehicle_remove_replacements_required', 'Select a replacement vehicle for each upcoming reservation.', $locale));
            }
        }

        $maxPrice = (float) ($target->vehicleType?->price ?? 0);

        try {
            DB::transaction(function () use ($user, $target, $replacementMap, $maxPrice, $candidates): void {
                // Lock target vehicle row (exists + owned).
                $lockedTarget = Vehicle::query()
                    ->where('user_id', $user->id)
                    ->whereKey($target->id)
                    ->lockForUpdate()
                    ->with('vehicleType')
                    ->firstOrFail();

                // Lock reservations we are updating.
                $reservationIds = array_keys($replacementMap);
                $lockedReservations = \App\Models\Reservation::query()
                    ->where('user_id', $user->id)
                    ->whereIn('id', $reservationIds)
                    ->lockForUpdate()
                    ->with(['pickUpTimeSlot', 'dropOffTimeSlot', 'vehicleType'])
                    ->get()
                    ->keyBy('id');

                if ($lockedReservations->count() !== count($reservationIds)) {
                    abort(422);
                }

                // Lock candidate vehicles.
                $candidateIds = array_values(array_unique(array_values($replacementMap)));
                $lockedVehicles = Vehicle::query()
                    ->where('user_id', $user->id)
                    ->whereIn('id', $candidateIds)
                    ->lockForUpdate()
                    ->with('vehicleType')
                    ->get()
                    ->keyBy('id');

                if ($lockedVehicles->count() !== count($candidateIds)) {
                    abort(422);
                }

                // Pairwise planned assignment validation.
                if (! $candidates->validateReplacementCombination($user, $replacementMap)) {
                    abort(422);
                }

                // For each reservation: verify still upcoming, still uses target, candidate is eligible and conflict-free.
                foreach ($replacementMap as $rid => $newVehicleId) {
                    /** @var \App\Models\Reservation $res */
                    $res = $lockedReservations->get($rid);
                    if (! $res || ! PanelReservationListService::isUpcoming($res)) {
                        abort(422);
                    }
                    if ((int) ($res->vehicle_id ?? 0) !== (int) $lockedTarget->id) {
                        abort(422);
                    }

                    /** @var Vehicle $newVehicle */
                    $newVehicle = $lockedVehicles->get($newVehicleId);
                    if (! $newVehicle || (int) $newVehicle->id === (int) $lockedTarget->id) {
                        abort(422);
                    }

                    $p = (float) ($newVehicle->vehicleType?->price ?? 0);
                    if ($p > $maxPrice + 0.000001) {
                        abort(422);
                    }

                    // Hard membership check: ensure selected vehicle is an actual candidate for this reservation.
                    $candidateList = $candidates->candidatesForReservation($user, $res, [
                        'exclude_vehicle_id' => (int) $lockedTarget->id,
                        'max_price' => $maxPrice,
                    ]);
                    $isMember = $candidateList->contains(fn (Vehicle $v) => (int) $v->id === (int) $newVehicle->id);
                    if (! $isMember) {
                        abort(422);
                    }

                    // Lock all same-date reservations for this candidate vehicle to prevent race.
                    $date = $res->reservation_date?->toDateString() ?? '';
                    \App\Models\Reservation::query()
                        ->where('user_id', $user->id)
                        ->where('vehicle_id', $newVehicle->id)
                        ->whereDate('reservation_date', $date)
                        ->lockForUpdate()
                        ->get();

                    if ($candidates->hasConflictWithUpcoming($user, $newVehicle->id, $res, ignoreReservationIds: [$res->id])) {
                        abort(422);
                    }
                }

                // Apply updates.
                foreach ($replacementMap as $rid => $newVehicleId) {
                    /** @var \App\Models\Reservation $res */
                    $res = $lockedReservations->get($rid);
                    /** @var Vehicle $newVehicle */
                    $newVehicle = $lockedVehicles->get($newVehicleId);

                    $res->update([
                        'vehicle_id' => $newVehicle->id,
                        'license_plate' => $newVehicle->license_plate,
                        'vehicle_type_id' => $newVehicle->vehicle_type_id,
                    ]);
                }

                // Finally delete target vehicle.
                $lockedTarget->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', UiText::t('panel', 'vehicle_remove_replace_failed', 'Vehicle could not be removed due to a conflict. Please try again.', $locale));
        }

        return redirect()->route('panel.vehicles')->with(
            'message',
            UiText::t('panel', 'vehicle_removed', 'Vehicle removed.', $locale)
        );
    }

    private function ownedVehicleOrFail(Request $request, int $id): Vehicle
    {
        return Vehicle::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();
    }
}
