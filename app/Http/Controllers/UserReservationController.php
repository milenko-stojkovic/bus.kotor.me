<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\View\View;

class UserReservationController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->string('date')->toString();
        $status = $request->string('status')->toString();

        $reservations = Reservation::query()
            ->with(['dropOffTimeSlot', 'pickUpTimeSlot', 'vehicle', 'vehicleType'])
            ->where('user_id', $request->user()->id)
            ->when($date !== '', fn ($q) => $q->whereDate('reservation_date', $date))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('reservation_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('profile.reservations.index', [
            'reservations' => $reservations,
            'filters' => [
                'date' => $date,
                'status' => $status,
            ],
        ]);
    }

    public function downloadInvoice(Request $request, int $id): BinaryFileResponse
    {
        $reservation = Reservation::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        abort_unless($reservation->invoice_pdf_path, 404);
        abort_unless(Storage::exists($reservation->invoice_pdf_path), 404);

        return response()->download(
            storage_path('app/'.$reservation->invoice_pdf_path),
            'invoice-'.$reservation->id.'.pdf'
        );
    }
}
