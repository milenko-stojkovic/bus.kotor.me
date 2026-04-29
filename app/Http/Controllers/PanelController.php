<?php

namespace App\Http\Controllers;

use App\Services\Reservation\PanelReservationListService;
use App\Services\Reservation\PanelStatisticsService;
use App\Services\Reservation\VehicleReplacementCandidateService;
use Illuminate\Http\Request;
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

    public function statistics(Request $request, PanelStatisticsService $statisticsService): View
    {
        return view('panel.statistics', $statisticsService->overview($request->user()));
    }
}
