<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\FzbrStoreRequest;
use App\Mail\AgencyFreeReservationRequestSubmittedMail;
use App\Models\AdminAlert;
use App\Models\FreeReservationRequest;
use App\Models\FreeReservationRequestAttachment;
use App\Models\FreeReservationRequestVehicle;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Reservation\ReservationBookingPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class FzbrController extends Controller
{
    public function create(Request $request, ReservationBookingPageData $pageData): View
    {
        /** @var User $user */
        $user = $request->user();

        $data = $pageData->forAuthenticated($request, $user);
        $locale = app()->getLocale();

        return view('panel.fzbr', [
            ...$data,
            'locale' => $locale,
            'userVehicles' => $user->vehicles()->with(['vehicleType.translations'])->orderBy('license_plate')->get(),
        ]);
    }

    public function store(FzbrStoreRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();
        $locale = app()->getLocale();

        $vehicleIds = array_values(array_unique(array_map('intval', $data['vehicles'] ?? [])));

        /** @var array{request: FreeReservationRequest, vehicles: list<FreeReservationRequestVehicle>, attachments: list<FreeReservationRequestAttachment>} $created */
        $created = DB::transaction(function () use ($data, $user, $locale, $vehicleIds): array {
            /** @var list<Vehicle> $vehicles */
            $vehicles = Vehicle::query()
                ->where('user_id', $user->id)
                ->whereIn('id', $vehicleIds)
                ->with(['vehicleType.translations'])
                ->orderBy('license_plate')
                ->get()
                ->all();

            if (count($vehicles) !== count($vehicleIds)) {
                abort(422, 'Invalid vehicles selection.');
            }

            $req = FreeReservationRequest::query()->create([
                'user_id' => $user->id,
                'locale' => $locale,
                'institution_name' => $user->name,
                'institution_email' => $user->email,
                'institution_phone' => null,
                'reservation_date' => $data['reservation_date'],
                'drop_off_time_slot_id' => (int) $data['drop_off_time_slot_id'],
                'pick_up_time_slot_id' => (int) $data['pick_up_time_slot_id'],
                'country' => (string) ($user->country ?? ''),
                'status' => FreeReservationRequest::STATUS_SUBMITTED,
            ]);

            $vehicleRows = [];
            foreach ($vehicles as $v) {
                $vt = $v->vehicleType;
                $vtName = $vt?->getTranslatedName('cg') ?: ($vt ? ('#'.$vt->id) : '#'.$v->vehicle_type_id);
                $vtDesc = trim((string) ($vt?->getTranslatedDescription('cg') ?? ''));
                $label = $vtDesc !== '' ? ($vtName.' ('.$vtDesc.')') : $vtName;

                $vehicleRows[] = FreeReservationRequestVehicle::query()->create([
                    'request_id' => $req->id,
                    'agency_vehicle_id' => $v->id,
                    'license_plate' => (string) $v->license_plate,
                    'vehicle_type_id' => (int) $v->vehicle_type_id,
                    'vehicle_type_label' => $label,
                ]);
            }

            $attachmentRows = [];
            /** @var array<int, \Illuminate\Http\UploadedFile> $docs */
            $docs = $data['documents'];
            foreach ($docs as $file) {
                $original = $file->getClientOriginalName();
                $mime = (string) ($file->getClientMimeType() ?? $file->getMimeType() ?? '');
                $size = (int) $file->getSize();
                $safeName = Str::slug(pathinfo($original, PATHINFO_FILENAME));
                $ext = strtolower((string) $file->getClientOriginalExtension());
                $storedName = Str::uuid()->toString().'_'.($safeName !== '' ? $safeName : 'document').($ext !== '' ? '.'.$ext : '');
                $path = 'free-reservation-requests/'.$req->id.'/'.$storedName;

                Storage::disk('local')->putFileAs('free-reservation-requests/'.$req->id, $file, $storedName);

                $attachmentRows[] = FreeReservationRequestAttachment::query()->create([
                    'request_id' => $req->id,
                    'original_name' => $original,
                    'stored_path' => $path,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                ]);
            }

            return [
                'request' => $req,
                'vehicles' => $vehicleRows,
                'attachments' => $attachmentRows,
            ];
        });

        $createdReq = $created['request'];
        $createdReq->load(['vehicles', 'dropOffTimeSlot', 'pickUpTimeSlot', 'attachments']);

        Mail::to('bus@kotor.me')->send(new AgencyFreeReservationRequestSubmittedMail($createdReq));

        AdminAlert::query()->create([
            'type' => 'free_reservation_request',
            'status' => AdminAlert::STATUS_UNREAD,
            'title' => 'Zahtjev za besplatnu rezervaciju',
            'message' => 'Došao je zahtjev za besplatnu rezervaciju od '.$user->name
                .'. Email '.$user->email
                .'. Vidi pod Besplatne rezervacije.',
            'payload_json' => [
                'free_reservation_request_id' => $createdReq->id,
                'source' => 'agency_panel_fzbr',
                'user_id' => $user->id,
            ],
        ]);

        return redirect()
            ->to(route('panel.fzbr.create', [], false))
            ->with('status', $locale === 'cg'
                ? 'Zahtjev je uspješno poslat. Administrator će Vas kontaktirati.'
                : 'Request submitted successfully. An administrator will contact you.');
    }
}

