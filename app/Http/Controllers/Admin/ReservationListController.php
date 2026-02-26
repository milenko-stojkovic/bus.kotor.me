<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin panel: pregled rezervacija za naredna 3 sata i pretraga (samo čitanje, bez izmena).
 */
class ReservationListController extends Controller
{
    /**
     * Spisak rezervacija za naredna 3 sata. Opciono pretraga po email, tablici, imenu, merchant_transaction_id.
     */
    public function index(Request $request): View
    {
        $q = $request->input('q');

        $reservations = Reservation::query()
            ->with(['dropOffTimeSlot', 'pickUpTimeSlot', 'vehicleType', 'user', 'postFiscalizationDataUnresolved'])
            ->when($q !== null && $q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('email', 'like', '%'.$q.'%')
                        ->orWhere('license_plate', 'like', '%'.$q.'%')
                        ->orWhere('user_name', 'like', '%'.$q.'%')
                        ->orWhere('merchant_transaction_id', 'like', '%'.$q.'%');
                });
            })
            ->when($q === null || $q === '', fn ($query) => $query->nextThreeHours())
            ->orderBy('reservation_date')
            ->orderBy('drop_off_time_slot_id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reservations.index', [
            'reservations' => $reservations,
            'search' => $q ?? '',
        ]);
    }
}
