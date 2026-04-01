<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateReservationVehicleRequest;
use App\Jobs\GenerateInvoicePdfJob;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\Reservation;
use App\Models\Vehicle;
use App\Services\Reservation\ReservationBookingPageData;
use App\Support\UiText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserReservationController extends Controller
{
    public function index(Request $request, ReservationBookingPageData $bookingPageData): View
    {
        $booking = $bookingPageData->forAuthenticated($request, $request->user());

        return view('panel.reservations', $booking);
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

    /**
     * PDF u browser tabu (inline), ista autorizacija kao download.
     */
    public function showInvoice(Request $request, int $id): BinaryFileResponse
    {
        $reservation = Reservation::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        abort_unless($reservation->invoice_pdf_path, 404);
        $fullPath = storage_path('app/'.$reservation->invoice_pdf_path);
        abort_unless(is_file($fullPath), 404);

        return response()->file($fullPath, [
            'Content-Disposition' => 'inline; filename="invoice-'.$reservation->id.'.pdf"',
        ]);
    }

    public function updateVehicle(UpdateReservationVehicleRequest $request, int $id): RedirectResponse
    {
        $reservation = Reservation::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $vehicle = Vehicle::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($request->validated('vehicle_id'))
            ->firstOrFail();

        $reservation->update([
            'vehicle_id' => $vehicle->id,
            'license_plate' => $vehicle->license_plate,
        ]);
        $reservation->refresh();

        if ($reservation->status === 'paid') {
            if ($reservation->invoice_pdf_path && Storage::exists($reservation->invoice_pdf_path)) {
                Storage::delete($reservation->invoice_pdf_path);
            }
            $reservation->update([
                'invoice_pdf_path' => null,
                'invoice_sent_at' => null,
                'email_sent' => 0,
            ]);
            $reservation->refresh();

            $isFiscal = $reservation->fiscal_jir !== null;
            GenerateInvoicePdfJob::withChain([
                new SendInvoiceEmailJob($reservation->id, $isFiscal),
            ])->dispatch($reservation->id, $isFiscal, true);
        }

        $locale = app()->getLocale();
        $messageKey = $reservation->status === 'paid' ? 'vehicle_change_success' : 'vehicle_change_success_free';

        return redirect()
            ->route('panel.upcoming')
            ->with(
                'message',
                UiText::t(
                    'panel',
                    $messageKey,
                    $reservation->status === 'paid'
                        ? 'Vehicle updated. A new invoice will be sent by email.'
                        : 'Vehicle updated.',
                    $locale
                )
            );
    }
}
