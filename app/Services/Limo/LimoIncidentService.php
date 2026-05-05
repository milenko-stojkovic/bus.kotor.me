<?php

namespace App\Services\Limo;

use App\Mail\LimoCommunalPoliceIncidentMail;
use App\Models\Admin;
use App\Models\AdminAlert;
use App\Models\LimoIncident;
use App\Models\User;
use App\Services\Reservation\DuplicateReservationAttemptService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class LimoIncidentService
{
    private const COMMUNAL_POLICE_EMAIL = 'komunalna.policija@kotor.me';

    /**
     * @param  array{
     *     type: string,
     *     license_plate?: string|null,
     *     agency_user_id?: int|null,
     *     visible_agency_name?: string|null,
     *     plate_photo: UploadedFile,
     *     branding_photo?: UploadedFile|null,
     *     note?: string|null,
     *     gps_lat?: float|string|null,
     *     gps_lng?: float|string|null,
     *     device_info?: string|null
     * }  $data
     */
    public function createIncident(array $data, Admin $evidenter): LimoIncident
    {
        $incidentUuid = (string) Str::uuid();
        $type = $data['type'];

        $plateSnapshot = null;
        if (! empty($data['license_plate'])) {
            $normalized = DuplicateReservationAttemptService::normalizeLicensePlate((string) $data['license_plate']);
            $plateSnapshot = $normalized !== '' ? $normalized : null;
        }

        $agencyUserId = isset($data['agency_user_id']) ? (int) $data['agency_user_id'] : null;
        $agencyNameSnapshot = null;
        $agencyEmailSnapshot = null;
        if ($agencyUserId !== null) {
            $agency = User::query()->find($agencyUserId);
            if ($agency !== null) {
                $agencyNameSnapshot = (string) $agency->name;
                $agencyEmailSnapshot = (string) $agency->email;
            } else {
                $agencyUserId = null;
            }
        }

        /** @var UploadedFile $plateFile */
        $plateFile = $data['plate_photo'];
        $brandingFile = $data['branding_photo'] ?? null;

        $dir = 'limo_incidents/'.$incidentUuid;
        $platePath = $this->storeImage($plateFile, $dir, 'plate');
        $brandingPath = $brandingFile instanceof UploadedFile
            ? $this->storeImage($brandingFile, $dir, 'branding')
            : null;

        $occurredAt = now();

        /** @var LimoIncident $incident */
        $incident = LimoIncident::query()->create([
            'incident_uuid' => $incidentUuid,
            'type' => $type,
            'license_plate_snapshot' => $plateSnapshot,
            'agency_user_id' => $agencyUserId,
            'agency_name_snapshot' => $agencyNameSnapshot,
            'agency_email_snapshot' => $agencyEmailSnapshot,
            'visible_agency_name' => $this->nullableNonEmpty(isset($data['visible_agency_name']) ? trim((string) $data['visible_agency_name']) : null),
            'plate_photo_path' => $platePath,
            'branding_photo_path' => $brandingPath,
            'note' => $this->nullableNonEmpty($data['note'] ?? null),
            'occurred_at' => $occurredAt,
            'gps_lat' => $this->nullableCoordinate($data['gps_lat'] ?? null),
            'gps_lng' => $this->nullableCoordinate($data['gps_lng'] ?? null),
            'recorded_by_limo_admin_id' => $evidenter->id,
            'device_info' => isset($data['device_info']) ? (string) $data['device_info'] : null,
            'communal_email_sent_at' => null,
            'admin_alert_id' => null,
        ]);

        Log::channel('payments')->info('limo_incident_created', [
            'incident_uuid' => $incidentUuid,
            'type' => $type,
            'license_plate' => $plateSnapshot,
            'agency_user_id' => $agencyUserId,
            'recorded_by_limo_admin_id' => (int) $evidenter->id,
        ]);

        $typeLabelCg = $this->typeLabelCg($type);
        $occurredAtFormatted = $occurredAt->clone()->timezone('Europe/Podgorica')->format('d.m.Y. H:i');
        $evidenterLabel = $evidenter->username.' ('.$evidenter->email.')';

        $emailSent = false;
        try {
            Mail::to(self::COMMUNAL_POLICE_EMAIL)->send(
                new LimoCommunalPoliceIncidentMail(
                    incident: $incident,
                    evidenterLabel: $evidenterLabel,
                    typeLabelCg: $typeLabelCg,
                    occurredAtFormatted: $occurredAtFormatted,
                ),
            );
            $incident->forceFill(['communal_email_sent_at' => now()])->save();
            $emailSent = true;
            Log::channel('payments')->info('limo_incident_communal_email_sent', [
                'incident_uuid' => $incidentUuid,
                'type' => $type,
                'license_plate' => $plateSnapshot,
                'agency_user_id' => $agencyUserId,
                'recorded_by_limo_admin_id' => (int) $evidenter->id,
            ]);
        } catch (Throwable $e) {
            Log::channel('payments')->error('limo_incident_communal_email_failed', [
                'incident_uuid' => $incidentUuid,
                'type' => $type,
                'license_plate' => $plateSnapshot,
                'agency_user_id' => $agencyUserId,
                'recorded_by_limo_admin_id' => (int) $evidenter->id,
                'message' => $e->getMessage(),
            ]);
        }

        $alert = AdminAlert::query()
            ->where('type', 'limo_incident')
            ->where('payload_json->incident_uuid', $incidentUuid)
            ->first();

        if ($alert === null) {
            $knownAgencyLine = $agencyNameSnapshot !== null
                ? $agencyNameSnapshot.($agencyEmailSnapshot !== null ? ' ('.$agencyEmailSnapshot.')' : '')
                : '—';

            $alert = AdminAlert::query()->create([
                'type' => 'limo_incident',
                'status' => AdminAlert::STATUS_UNREAD,
                'title' => 'Limo incident: '.$typeLabelCg,
                'message' => 'Tablica: '.($plateSnapshot ?? '—')
                    .'; vidljiva agencija: '.($incident->visible_agency_name ?? '—')
                    .'; poznata agencija: '.$knownAgencyLine
                    .'; vrijeme: '.$occurredAtFormatted
                    .'; UUID: '.$incidentUuid
                    .'; email KP: '.($emailSent ? 'poslat' : 'nije poslat'),
                'payload_json' => [
                    'incident_uuid' => $incidentUuid,
                    'type' => $type,
                    'license_plate' => $plateSnapshot,
                    'visible_agency_name' => $incident->visible_agency_name,
                    'known_agency_name' => $agencyNameSnapshot,
                    'known_agency_email' => $agencyEmailSnapshot,
                    'occurred_at' => $occurredAt->toIso8601String(),
                    'communal_email_sent' => $emailSent,
                ],
            ]);

            Log::channel('payments')->info('limo_incident_admin_alert_created', [
                'incident_uuid' => $incidentUuid,
                'type' => $type,
                'license_plate' => $plateSnapshot,
                'agency_user_id' => $agencyUserId,
                'recorded_by_limo_admin_id' => (int) $evidenter->id,
            ]);
        }

        if ($incident->admin_alert_id !== (int) $alert->id) {
            $incident->forceFill(['admin_alert_id' => $alert->id])->save();
        }

        return $incident->fresh();
    }

    private function storeImage(UploadedFile $file, string $directory, string $prefix): string
    {
        $ext = strtolower($file->guessExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }
        $name = $prefix.'_'.Str::uuid()->toString().'.'.$ext;

        return $file->storeAs($directory, $name, 'local');
    }

    private function nullableCoordinate(float|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (string) $value : null;
    }

    private function nullableNonEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $t = trim($value);

        return $t === '' ? null : $t;
    }

    private function typeLabelCg(string $type): string
    {
        return match ($type) {
            LimoIncident::TYPE_QR_INSUFFICIENT_FUNDS => 'Nedovoljan avans (QR)',
            LimoIncident::TYPE_PLATE_INSUFFICIENT_FUNDS => 'Nedovoljan avans (tablica)',
            LimoIncident::TYPE_UNREGISTERED_VEHICLE_WITH_BRANDING => 'Neregistrovano vozilo sa brendingom',
            LimoIncident::TYPE_INVALID_QR_TOKEN => 'Nevažeći QR token',
            LimoIncident::TYPE_DRIVER_NON_COOPERATIVE => 'Vozač ne saradjuje',
            default => $type,
        };
    }
}
