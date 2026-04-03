<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Http\Requests\Control\ControlReservationSearchRequest;
use App\Models\Reservation;
use App\Models\VehicleType;
use App\Services\Control\ControlArrivalSlots;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ControlDashboardController extends Controller
{
    public function index(ControlReservationSearchRequest $request, ControlArrivalSlots $arrivals): View|RedirectResponse
    {
        if ($request->submittedSearch() && ! $request->hasSearchCriteria()) {
            return redirect()
                ->to(route('control.dashboard', [], false))
                ->withErrors([
                    'search' => 'Unesite bar jedan kriterijum.',
                ])
                ->withInput($request->except('search'));
        }

        $arrivalGroups = $arrivals->groupsWithinNextHours(3);

        $searchResults = null;
        if ($request->hasSearchCriteria()) {
            $searchResults = $this->searchReservations($request);
        }

        $vehicleTypes = VehicleType::query()
            ->with('translations')
            ->orderBy('id')
            ->get();

        return view('control.dashboard', [
            'arrivalGroups' => $arrivalGroups,
            'searchResults' => $searchResults,
            'vehicleTypes' => $vehicleTypes,
            'searchInput' => $request->only(['date', 'name', 'email', 'vehicle_type_id', 'license_plate']),
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Reservation>
     */
    private function searchReservations(ControlReservationSearchRequest $request)
    {
        $q = Reservation::query()
            ->with(['pickUpTimeSlot', 'dropOffTimeSlot', 'vehicleType.translations'])
            ->whereDate('reservation_date', '>=', now()->startOfDay());

        if ($request->filled('date')) {
            $q->whereDate('reservation_date', $request->date('date'));
        }

        if ($request->filled('name')) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->input('name')).'%';
            $q->where('user_name', 'like', $term);
        }

        if ($request->filled('email')) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->input('email')).'%';
            $q->where('email', 'like', $term);
        }

        if ($request->filled('vehicle_type_id')) {
            $q->where('vehicle_type_id', (int) $request->input('vehicle_type_id'));
        }

        if ($request->filled('license_plate')) {
            $raw = (string) $request->input('license_plate');
            $q->whereRaw('LOWER(license_plate) like ?', ['%'.strtolower(str_replace(['%', '_'], ['\\%', '\\_'], $raw)).'%']);
        }

        return $q
            ->orderBy('reservation_date')
            ->orderBy('id')
            ->get();
    }
}
