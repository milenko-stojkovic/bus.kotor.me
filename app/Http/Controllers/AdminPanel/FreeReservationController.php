<?php

namespace App\Http\Controllers\AdminPanel;

use App\Exceptions\AdminFreeReservationSlotsUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPanel\AdminFreeReservationRequest;
use App\Services\AdminPanel\FreeReservation\AdminDirectFreeReservationService;
use App\Services\Reservation\ReservationBookingPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

class FreeReservationController extends Controller
{
    public function create(Request $request, ReservationBookingPageData $pageData): View
    {
        App::setLocale('cg');

        return view('admin-panel.free-reservations', array_merge(
            $pageData->forAdminPanel($request),
            [
                'navActive' => 'free-reservations',
                'pageTitle' => 'Besplatne rezervacije',
            ]
        ));
    }

    public function store(
        AdminFreeReservationRequest $request,
        AdminDirectFreeReservationService $service,
    ): RedirectResponse {
        App::setLocale('cg');

        try {
            $service->create($request->validated());
        } catch (AdminFreeReservationSlotsUnavailableException) {
            return redirect()->to(route('panel_admin.free-reservations', [
                'name' => $request->input('name'),
                'country' => $request->input('country'),
                'license_plate' => $request->input('license_plate'),
                'email' => $request->input('email'),
                'vehicle_type_id' => $request->input('vehicle_type_id'),
            ], false))->with(
                'error',
                'Izabrani termini više nisu dostupni (zauzeti ili blokirani). Svi unosi su sačuvani osim datuma i vremena — izaberite ponovo termine.'
            );
        }

        return redirect()->to(route('panel_admin.free-reservations', [], false))
            ->with(
                'status',
                'Besplatna rezervacija je kreirana. Potvrda sa PDF-om biće poslata na navedeni email.'
            );
    }
}
