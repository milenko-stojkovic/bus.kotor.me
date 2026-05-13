<?php

namespace App\Http\Controllers\Limo;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\LimoIncident;
use App\Models\LimoPlateUpload;
use App\Services\Limo\LimoIncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LimoIncidentController extends Controller
{
    private const INVALID_UPLOAD_RESPONSE = [
        'status' => 'error',
        'code' => 'invalid_upload',
        'message' => 'Fotografija više nije važeća. Pokušajte ponovo.',
    ];

    public function store(Request $request, LimoIncidentService $incidentService): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => ['required', 'string', Rule::in(LimoIncident::TYPES)],
                'license_plate' => ['sometimes', 'nullable', 'string', 'max:50'],
                'agency_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
                'visible_agency_name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'plate_photo' => ['required', 'file', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
                'branding_photo' => ['sometimes', 'nullable', 'file', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
                'note' => ['sometimes', 'nullable', 'string', 'max:10000'],
                'gps_lat' => ['sometimes', 'nullable', 'numeric'],
                'gps_lng' => ['sometimes', 'nullable', 'numeric'],
                'device_info' => ['sometimes', 'nullable', 'string', 'max:2000'],
            ]);
        } catch (ValidationException) {
            return response()->json([
                'status' => 'error',
                'code' => 'validation_error',
                'message' => 'Došlo je do greške. Pokušajte ponovo.',
            ], 422);
        }

        $admin = $request->user('panel_admin');
        if (! $admin instanceof Admin) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $incident = $incidentService->createIncident($validated, $admin);

        return response()->json([
            'status' => 'ok',
            'incident_uuid' => $incident->incident_uuid,
            'communal_email_sent' => $incident->communal_email_sent_at !== null,
        ]);
    }

    public function storeFromPlateUpload(Request $request, LimoIncidentService $incidentService): JsonResponse
    {
        try {
            $validated = $request->validate([
                'upload_token' => ['required', 'string'],
                'type' => ['required', 'string', Rule::in(LimoIncident::TYPES)],
                'license_plate' => ['sometimes', 'nullable', 'string', 'max:50'],
                'visible_agency_name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'branding_photo' => ['sometimes', 'nullable', 'file', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
                'note' => ['sometimes', 'nullable', 'string', 'max:10000'],
            ]);
        } catch (ValidationException) {
            return response()->json([
                'status' => 'error',
                'code' => 'validation_error',
                'message' => 'Došlo je do greške. Pokušajte ponovo.',
            ], 422);
        }

        $admin = $request->user('panel_admin');
        if (! $admin instanceof Admin) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        /** @var LimoPlateUpload|null $upload */
        $upload = LimoPlateUpload::query()
            ->where('upload_token', (string) $validated['upload_token'])
            ->first();

        if ($upload === null) {
            return response()->json(self::INVALID_UPLOAD_RESPONSE, 422);
        }

        if ((int) $upload->uploaded_by_limo_admin_id !== (int) $admin->id) {
            return response()->json(self::INVALID_UPLOAD_RESPONSE, 422);
        }

        if ($upload->expires_at !== null && $upload->expires_at->isPast()) {
            return response()->json(self::INVALID_UPLOAD_RESPONSE, 422);
        }

        if (! $incidentService->plateUploadFileExists($upload)) {
            return response()->json(self::INVALID_UPLOAD_RESPONSE, 422);
        }

        $incident = $incidentService->createIncidentFromPlateUpload($upload, $validated, $admin);

        return response()->json([
            'status' => 'ok',
            'incident_uuid' => $incident->incident_uuid,
            'communal_email_sent' => $incident->communal_email_sent_at !== null,
        ]);
    }
}
