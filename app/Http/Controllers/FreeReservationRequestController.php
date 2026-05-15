<?php

namespace App\Http\Controllers;

use App\Http\Requests\FreeReservationRequestStoreRequest;
use App\Mail\FreeReservationRequestSubmittedMail;
use App\Models\FreeReservationRequest;
use App\Models\FreeReservationRequestVehicle;
use App\Services\Reservation\ReservationBookingPageData;
use App\Support\UiText;
use Illuminate\Http\RedirectResponse;
use App\Services\AdminPanel\AdminAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class FreeReservationRequestController extends Controller
{
    public function create(\Illuminate\Http\Request $request, ReservationBookingPageData $pageData): View
    {
        $data = $pageData->forGuest($request);
        $locale = app()->getLocale();

        return view('free-reservation-request.create', [
            ...$data,
            'locale' => $locale,
        ]);
    }

    public function store(FreeReservationRequestStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $locale = app()->getLocale();

        /** @var FreeReservationRequest $created */
        $created = DB::transaction(function () use ($data, $locale): FreeReservationRequest {
            $req = FreeReservationRequest::query()->create([
                'locale' => $locale,
                'institution_name' => $data['institution_name'],
                'institution_email' => $data['institution_email'],
                'institution_phone' => $data['institution_phone'],
                'reservation_date' => $data['reservation_date'],
                'drop_off_time_slot_id' => (int) $data['drop_off_time_slot_id'],
                'pick_up_time_slot_id' => (int) $data['pick_up_time_slot_id'],
                'country' => $data['country'],
                'status' => FreeReservationRequest::STATUS_SUBMITTED,
            ]);

            foreach ($data['vehicles'] as $v) {
                FreeReservationRequestVehicle::query()->create([
                    'request_id' => $req->id,
                    'license_plate' => (string) $v['license_plate'],
                    'vehicle_type_id' => (int) $v['vehicle_type_id'],
                ]);
            }

            return $req;
        });

        $created->load(['vehicles.vehicleType.translations', 'vehicles', 'dropOffTimeSlot', 'pickUpTimeSlot']);

        $alertService = app(AdminAlertService::class);
        $alertService->createOnce(
            'free_reservation_request',
            'Zahtjev za besplatnu rezervaciju',
            'Došao je zahtjev za besplatnu rezervaciju od '.$created->institution_name
                .'. Telefon '.$created->institution_phone
                .'. Email '.$created->institution_email
                .'. Vidi pod Besplatne rezervacije.',
            'medium',
            'free_reservation_request:'.$created->id,
            [
                'free_reservation_request_id' => $created->id,
                'source' => 'guest_free_reservation_request',
            ],
        );

        try {
            Mail::to('bus@kotor.me')->send(new FreeReservationRequestSubmittedMail($created));
        } catch (Throwable $e) {
            Log::channel('payments')->warning('fzbr_guest_submit_mail_failed', [
                'free_reservation_request_id' => $created->id,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
            $alertService->createOnce(
                'fzbr_request_unnotified',
                'FZBR: zahtjev u bazi bez email obavještenja',
                'Zahtjev ID '.$created->id.'. '.$created->institution_name.' / '.$created->institution_email,
                'high',
                'free_reservation_request:'.$created->id.':unnotified',
                [
                    'free_reservation_request_id' => $created->id,
                    'source' => 'guest_free_reservation_request',
                ],
            );
        }

        $success = UiText::t(
            'free_request',
            'success_message',
            'Zahtjev je uspješno poslat. Administrator će Vas kontaktirati.',
            $locale
        );

        return redirect()
            ->route('free-request.success')
            ->with('free_request_success', $success);
    }

    public function success(): View
    {
        $message = session('free_request_success')
            ?? UiText::t('free_request', 'success_message', 'Zahtjev je uspješno poslat. Administrator će Vas kontaktirati.');

        return view('free-reservation-request.success', [
            'message' => $message,
        ]);
    }
}

