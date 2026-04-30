<?php

namespace App\Http\Controllers;

use App\Http\Requests\Panel\PanelStatisticsRequest;
use App\Services\Reservation\PanelReservationListService;
use App\Services\Reservation\PanelStatisticsDateBounds;
use App\Services\Reservation\PanelStatisticsService;
use App\Services\Reservation\VehicleReplacementCandidateService;
use Illuminate\View\View;

class PanelController extends Controller
{
    public function upcoming(Request $request, PanelReservationListService $lists, VehicleReplacementCandidateService $candidates): View
    {
        $user = $request->user();
        $upcoming = $lists->upcomingFor($user);
        $vehicles = $user->vehicles()
            ->where('status', \App\Models\Vehicle::STATUS_ACTIVE)
            ->with('vehicleType')
            ->orderBy('license_plate')
            ->get();

        $allowedByReservationId = [];
        foreach ($upcoming as $r) {
            $categoryMaxPrice = (float) ($r->vehicleType?->price ?? 0);
            $allowedByReservationId[(int) $r->id] = $candidates->candidatesForReservation($user, $r, [
                'max_price' => $categoryMaxPrice,
            ]);
        }

        return view('panel.upcoming', [
            'reservations' => $upcoming,
            'vehicles' => $vehicles,
            'allowedVehiclesByReservationId' => $allowedByReservationId,
        ]);
    }

    public function realized(Request $request, PanelReservationListService $lists): View
    {
        return view('panel.realized', [
            'reservations' => $lists->realizedFor($request->user()),
        ]);
    }

    public function statistics(PanelStatisticsRequest $request, PanelStatisticsService $statisticsService): View
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $bounds = app(PanelStatisticsDateBounds::class);
        $min = $bounds->minDateFor($user);
        $max = $bounds->maxDateFor($user);

        $validated = $request->validated();

        $from = isset($validated['date_from']) && is_string($validated['date_from']) && $validated['date_from'] !== ''
            ? \Carbon\Carbon::parse($validated['date_from'], $min->getTimezone())->startOfDay()
            : $min->copy();
        $to = isset($validated['date_to']) && is_string($validated['date_to']) && $validated['date_to'] !== ''
            ? \Carbon\Carbon::parse($validated['date_to'], $min->getTimezone())->startOfDay()
            : $max->copy();

        // Clamp to bounds (closed interval).
        if ($from->lt($min)) {
            $from = $min->copy();
        }
        if ($to->gt($max)) {
            $to = $max->copy();
        }
        if ($from->gt($to)) {
            $from = $min->copy();
            $to = $max->copy();
        }

        return view('panel.statistics', [
            ...$statisticsService->overview($user, $from, $to),
            'minDate' => $min->toDateString(),
            'maxDate' => $max->toDateString(),
        ]);
    }
}
