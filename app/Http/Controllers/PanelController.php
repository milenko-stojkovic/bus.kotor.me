<?php

namespace App\Http\Controllers;

use App\Services\Reservation\PanelReservationListService;
use App\Services\Reservation\PanelStatisticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PanelController extends Controller
{
    public function upcoming(Request $request, PanelReservationListService $lists): View
    {
        $user = $request->user();

        return view('panel.upcoming', [
            'reservations' => $lists->upcomingFor($user),
            'vehicles' => $user->vehicles()->with('vehicleType')->orderBy('license_plate')->get(),
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
