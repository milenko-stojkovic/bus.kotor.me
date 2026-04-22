<?php

namespace App\Http\Controllers\AdminPanel;

use App\Exceptions\AdminFreeReservationSlotsUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPanel\AdminFreeReservationRequest;
use App\Http\Requests\AdminPanel\AdminFreeReservationRequestFulfillRequest;
use App\Http\Requests\AdminPanel\AdminFreeReservationRequestRejectRequest;
use App\Http\Requests\AdminPanel\AdminFreeReservationRequestUpdateRequest;
use App\Models\FreeReservationRequest;
use App\Services\AdminPanel\FreeReservation\FreeReservationRequestAvailability;
use App\Services\AdminPanel\FreeReservation\FreeReservationRequestFulfillmentService;
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

        $requests = FreeReservationRequest::query()
            ->whereIn('status', [
                FreeReservationRequest::STATUS_SUBMITTED,
                FreeReservationRequest::STATUS_UPDATED,
            ])
            ->with([
                'dropOffTimeSlot',
                'pickUpTimeSlot',
                'vehicles.vehicleType.translations',
            ])
            ->orderByDesc('created_at')
            ->get();

        // Attach availability flags for UI (no extra queries per card beyond eager-loaded vehicles).
        $availability = app(FreeReservationRequestAvailability::class);
        foreach ($requests as $r) {
            $a = $availability->forRequest($r);
            $r->setAttribute('can_fulfill', (bool) $a['can_fulfill']);
            $r->setAttribute('required_vehicles', (int) $a['required']);
            $r->setAttribute('min_available', $a['min_available']);
        }

        return view('admin-panel.free-reservations', array_merge(
            $pageData->forAdminPanel($request),
            [
                'navActive' => 'free-reservations',
                'pageTitle' => 'Besplatne rezervacije',
                'freeReservationRequests' => $requests,
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

    public function fulfillRequest(
        AdminFreeReservationRequestFulfillRequest $request,
        FreeReservationRequest $freeReservationRequest,
        FreeReservationRequestFulfillmentService $service,
    ): RedirectResponse {
        App::setLocale('cg');

        try {
            $result = $service->fulfill($freeReservationRequest);
        } catch (AdminFreeReservationSlotsUnavailableException) {
            return back()->with('error', 'Kapacitet više nije dostupan za traženi datum/termine. Nijedna rezervacija nije kreirana.');
        }

        if (! $result['mail_sent']) {
            return back()->with('error', 'Rezervacije su kreirane, ali slanje potvrda nije uspelo. Pokušajte ponovo kasnije (zahtjev i upozorenje ostaju).');
        }

        return back()->with('status', 'Zahtjev je obrađen. Kreirane su besplatne rezervacije i potvrde su poslate na email iz zahtjeva.');
    }

    public function updateRequest(
        AdminFreeReservationRequestUpdateRequest $request,
        FreeReservationRequest $freeReservationRequest,
        FreeReservationRequestAvailability $availability,
    ): RedirectResponse {
        App::setLocale('cg');

        // Final availability check for requested vehicle count.
        $freeReservationRequest->loadMissing('vehicles');
        $required = (int) $freeReservationRequest->vehicles->count();
        $date = (string) $request->validated('reservation_date');
        $drop = (int) $request->validated('drop_off_time_slot_id');
        $pick = (int) $request->validated('pick_up_time_slot_id');

        // Reuse helper by temporarily setting fields for computation (no save yet).
        $clone = clone $freeReservationRequest;
        $clone->reservation_date = \Carbon\Carbon::parse($date);
        $clone->drop_off_time_slot_id = $drop;
        $clone->pick_up_time_slot_id = $pick;
        $clone->setRelation('vehicles', $freeReservationRequest->vehicles);

        $a = $availability->forRequest($clone);
        if (! $a['can_fulfill'] || $required < 1) {
            return back()->with('error', 'Za izabrani datum i termine nema dovoljno slobodnih kapaciteta za ovaj zahtjev.');
        }

        $freeReservationRequest->update([
            'reservation_date' => $date,
            'drop_off_time_slot_id' => $drop,
            'pick_up_time_slot_id' => $pick,
            'status' => FreeReservationRequest::STATUS_UPDATED,
        ]);

        return back()->with('status', 'Zahtjev je izmijenjen.');
    }

    public function rejectRequest(
        AdminFreeReservationRequestRejectRequest $request,
        FreeReservationRequest $freeReservationRequest,
    ): RedirectResponse {
        App::setLocale('cg');

        // Remove warning pointers then hard-delete request.
        \App\Models\AdminAlert::query()
            ->where('type', 'free_reservation_request')
            ->whereNull('removed_at')
            ->where('payload_json->free_reservation_request_id', $freeReservationRequest->id)
            ->update([
                'status' => \App\Models\AdminAlert::STATUS_DONE,
                'resolved_at' => now(),
                'removed_at' => now(),
            ]);

        $freeReservationRequest->delete();

        return back()->with('status', 'Zahtjev je odbačen i uklonjen.');
    }
}
