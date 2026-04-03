<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\Reservation\PanelReservationListService;
use App\Support\UiText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            return redirect()
                ->route('panel.vehicles')
                ->withErrors([
                    'vehicle_remove_blocked' => UiText::t(
                        'panel',
                        'vehicle_remove_blocked_upcoming',
                        'This vehicle is used in upcoming reservations. Please switch those reservations to another vehicle before removing it.',
                        $locale
                    ),
                ]);
        }

        $model->delete();

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
