<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use App\Models\VehicleType;
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

        return view('profile.vehicles.index', [
            'vehicles' => $vehicles,
            'vehicleTypes' => $vehicleTypes,
        ]);
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $request->user()->vehicles()->create($request->validated());

        return redirect()->route('profile.vehicles.index')->with('message', __('Vozilo je dodato.'));
    }

    public function update(UpdateVehicleRequest $request, int $vehicle): RedirectResponse
    {
        $model = $this->ownedVehicleOrFail($request, $vehicle);
        $model->update($request->validated());

        return redirect()->route('profile.vehicles.index')->with('message', __('Vozilo je ažurirano.'));
    }

    public function destroy(Request $request, int $vehicle): RedirectResponse
    {
        $model = $this->ownedVehicleOrFail($request, $vehicle);
        $model->delete();

        return redirect()->route('profile.vehicles.index')->with('message', __('Vozilo je obrisano.'));
    }

    private function ownedVehicleOrFail(Request $request, int $id): Vehicle
    {
        return Vehicle::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();
    }
}
