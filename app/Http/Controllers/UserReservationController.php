<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateReservationVehicleRequest;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\Reservation;
use App\Models\Vehicle;
use App\Services\Pdf\FreeReservationPdfGenerator;
use App\Services\Pdf\PaidInvoicePdfGenerator;
use App\Services\Reservation\ReservationBookingPageData;
use App\Support\UiText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserReservationController extends Controller
{
    public function index(Request $request, ReservationBookingPageData $bookingPageData): View
    {
        $booking = $bookingPageData->forAuthenticated($request, $request->user());

        return view('panel.reservations', $booking);
    }

    public function downloadInvoice(Request $request, int $id): StreamedResponse
    {
        $reservation = Reservation::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $binary = $this->pdfBinaryForReservation($reservation);
        abort_if($binary === '', 404);

        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary;
            },
            'invoice-'.$reservation->id.'.pdf',
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }

    /**
     * PDF u browser tabu (inline), generisan na zahtev.
     */
    public function showInvoice(Request $request, int $id): \Illuminate\Http\Response
    {
        $reservation = Reservation::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $binary = $this->pdfBinaryForReservation($reservation);
        abort_if($binary === '', 404);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-'.$reservation->id.'.pdf"',
        ]);
    }

    private function pdfBinaryForReservation(Reservation $reservation): string
    {
        if ($reservation->status === 'free') {
            try {
                return app(FreeReservationPdfGenerator::class)->renderBinary($reservation);
            } catch (\Throwable $e) {
                report($e);
                abort(503);
            }
        }
        if ($reservation->status === 'paid') {
            $isFiscal = $reservation->fiscal_jir !== null;
            try {
                return app(PaidInvoicePdfGenerator::class)->renderBinary($reservation, $isFiscal);
            } catch (\Throwable $e) {
                report($e);
                abort(503);
            }
        }

        abort(404);
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
            // Snapshot tipa vozila mora da prati izabrano vozilo,
            // ali `invoice_amount` ostaje nepromijenjen (historijska tačnost cijene).
            'vehicle_type_id' => $vehicle->vehicle_type_id,
        ]);
        $reservation->refresh();

        if ($reservation->status === 'paid') {
            $reservation->update([
                'invoice_sent_at' => null,
                'email_sent' => Reservation::EMAIL_NOT_SENT,
            ]);
            $reservation->refresh();

            $isFiscal = $reservation->fiscal_jir !== null;
            SendInvoiceEmailJob::dispatch($reservation->id, $isFiscal);
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
