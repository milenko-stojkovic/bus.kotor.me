<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\FzbrStoreRequest;
use App\Mail\AgencyFreeReservationRequestSubmittedMail;
use App\Models\AdminAlert;
use App\Models\FreeReservationRequest;
use App\Models\FreeReservationRequestAttachment;
use App\Models\FreeReservationRequestSegment;
use App\Models\FreeReservationRequestVehicle;
use App\Models\ListOfTimeSlot;
use App\Models\SystemConfig;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Reservation\ReservationBookingPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class FzbrController extends Controller
{
    private function maxVehiclesPerSegment(): int
    {
        $max = (int) SystemConfig::availableParkingSlots();
        if ($max <= 0) {
            return 9;
        }

        return $max;
    }

    public function create(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $locale = app()->getLocale();

        return view('panel.fzbr', [
            'locale' => $locale,
            'userVehicles' => $user->vehicles()
                ->where('status', \App\Models\Vehicle::STATUS_ACTIVE)
                ->with(['vehicleType.translations'])
                ->orderBy('license_plate')
                ->get(),
            'maxVehiclesPerSegment' => $this->maxVehiclesPerSegment(),
        ]);
    }

    public function slots(Request $request, ReservationBookingPageData $pageData): JsonResponse
    {
        $locale = app()->getLocale();

        $dateStr = (string) $request->query('reservation_date', '');
        $arrivalId = $request->query('drop_off_time_slot_id') !== null ? (int) $request->query('drop_off_time_slot_id') : null;
        $departureId = $request->query('pick_up_time_slot_id') !== null ? (int) $request->query('pick_up_time_slot_id') : null;

        $maxVehicles = $this->maxVehiclesPerSegment();
        $requiredRaw = $request->query('required');
        $required = is_numeric($requiredRaw) ? (int) $requiredRaw : 1;
        if ($required < 1) {
            $required = 1;
        }
        if ($required > $maxVehicles) {
            $required = $maxVehicles;
        }

        // FZBR can reserve multiple vehicles in one segment; capacity checks must reflect required count.
        // Also: for FZBR we apply capacity constraints even for "free window" slots (unlike standard 1-vehicle booking UI).
        $payload = $pageData->slotPayload($dateStr, $arrivalId, $departureId, $locale, $required, true);

        return response()->json($payload);
    }

    public function store(FzbrStoreRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();
        $locale = app()->getLocale();

        /** @var array<int, array{drop_off_time_slot_id:int,pick_up_time_slot_id:int,vehicles:list<int>}> $segmentsIn */
        $segmentsIn = array_values(array_map(function ($seg): array {
            $vehicles = is_array($seg['vehicles'] ?? null) ? $seg['vehicles'] : [];
            $vehicleIds = array_values(array_unique(array_map('intval', $vehicles)));
            $vehicleIds = array_values(array_filter($vehicleIds, fn (int $v) => $v > 0));

            return [
                'drop_off_time_slot_id' => (int) ($seg['drop_off_time_slot_id'] ?? 0),
                'pick_up_time_slot_id' => (int) ($seg['pick_up_time_slot_id'] ?? 0),
                'vehicles' => $vehicleIds,
            ];
        }, is_array($data['segments'] ?? null) ? $data['segments'] : []));

        $allVehicleIds = [];
        foreach ($segmentsIn as $seg) {
            foreach ($seg['vehicles'] as $vid) {
                $allVehicleIds[] = (int) $vid;
            }
        }
        $allVehicleIds = array_values(array_unique($allVehicleIds));

        /** @var array{request: FreeReservationRequest, segments: list<FreeReservationRequestSegment>, vehicles: list<FreeReservationRequestVehicle>, attachments: list<FreeReservationRequestAttachment>} $created */
        $created = DB::transaction(function () use ($data, $user, $locale, $segmentsIn, $allVehicleIds): array {
            /** @var list<Vehicle> $vehicles */
            $vehicles = Vehicle::query()
                ->where('user_id', $user->id)
                ->whereIn('id', $allVehicleIds)
                ->with(['vehicleType.translations'])
                ->orderBy('license_plate')
                ->get()
                ->all();

            if (count($vehicles) !== count($allVehicleIds)) {
                abort(422, 'Invalid vehicles selection.');
            }

            $vehiclesById = [];
            foreach ($vehicles as $v) {
                $vehiclesById[(int) $v->id] = $v;
            }

            $firstSeg = $segmentsIn[0] ?? null;
            if (! $firstSeg) {
                abort(422, 'Missing segments.');
            }

            $req = FreeReservationRequest::query()->create([
                'user_id' => $user->id,
                'locale' => $locale,
                'institution_name' => $user->name,
                'institution_email' => $user->email,
                'institution_phone' => null,
                'reservation_date' => $data['reservation_date'],
                // Legacy fields: keep in sync with the first segment for backwards compatibility.
                'drop_off_time_slot_id' => (int) $firstSeg['drop_off_time_slot_id'],
                'pick_up_time_slot_id' => (int) $firstSeg['pick_up_time_slot_id'],
                'country' => (string) ($user->country ?? ''),
                'status' => FreeReservationRequest::STATUS_SUBMITTED,
            ]);

            $segmentRows = [];
            $vehicleRows = [];

            foreach ($segmentsIn as $idx => $seg) {
                $segment = FreeReservationRequestSegment::query()->create([
                    'request_id' => $req->id,
                    'reservation_date' => $data['reservation_date'],
                    'drop_off_time_slot_id' => (int) $seg['drop_off_time_slot_id'],
                    'pick_up_time_slot_id' => (int) $seg['pick_up_time_slot_id'],
                    'position' => $idx + 1,
                ]);
                $segmentRows[] = $segment;

                foreach ($seg['vehicles'] as $vid) {
                    $v = $vehiclesById[(int) $vid] ?? null;
                    if (! $v) {
                        abort(422, 'Invalid vehicles selection.');
                    }

                    $vt = $v->vehicleType;
                    $vtName = $vt?->getTranslatedName('cg') ?: ($vt ? ('#'.$vt->id) : '#'.$v->vehicle_type_id);
                    $vtDesc = trim((string) ($vt?->getTranslatedDescription('cg') ?? ''));
                    $label = $vtDesc !== '' ? ($vtName.' ('.$vtDesc.')') : $vtName;

                    $vehicleRows[] = FreeReservationRequestVehicle::query()->create([
                        'request_id' => $req->id,
                        'segment_id' => $segment->id,
                        'agency_vehicle_id' => $v->id,
                        'license_plate' => (string) $v->license_plate,
                        'vehicle_type_id' => (int) $v->vehicle_type_id,
                        'vehicle_type_label' => $label,
                    ]);
                }
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
                'segments' => $segmentRows,
                'vehicles' => $vehicleRows,
                'attachments' => $attachmentRows,
            ];
        });

        $createdReq = $created['request'];
        $createdReq->load(['segments.vehicles', 'segments.dropOffTimeSlot', 'segments.pickUpTimeSlot', 'attachments']);

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

