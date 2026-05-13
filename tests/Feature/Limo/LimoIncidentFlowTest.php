<?php

namespace Tests\Feature\Limo;

use App\Mail\LimoCommunalPoliceIncidentMail;
use App\Models\Admin;
use App\Models\AdminAlert;
use App\Models\AgencyAdvanceTransaction;
use App\Models\LimoIncident;
use App\Models\LimoPlateUpload;
use App\Models\LimoPickupEvent;
use App\Models\ReportEmail;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Testing\Fakes\MailFake;
use RuntimeException;
use Tests\TestCase;

class LimoIncidentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makePlateUpload(Admin $admin, array $attrs = []): LimoPlateUpload
    {
        Storage::disk('local')->put('limo_plate_uploads/upl.jpg', 'plate-bytes');

        return LimoPlateUpload::query()->create(array_merge([
            'upload_token' => $attrs['upload_token'] ?? 'tok_'.str_repeat('A', 40),
            'path' => $attrs['path'] ?? 'limo_plate_uploads/upl.jpg',
            'ocr_text' => $attrs['ocr_text'] ?? null,
            'gps_lat' => $attrs['gps_lat'] ?? '42.1234567',
            'gps_lng' => $attrs['gps_lng'] ?? '18.7654321',
            'device_info' => $attrs['device_info'] ?? '{"t":1}',
            'uploaded_by_limo_admin_id' => $admin->id,
            'expires_at' => $attrs['expires_at'] ?? now()->addHour(),
            'consumed_at' => $attrs['consumed_at'] ?? null,
        ], $attrs));
    }

    private function makeLimoAdmin(string $username): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'email' => $username.'@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => false,
            'limo_access' => true,
        ]);
    }

    public function test_cannot_create_incident_without_plate_photo(): void
    {
        Storage::fake('local');
        $admin = $this->makeLimoAdmin('limo_inc_a');

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident', [
                'type' => LimoIncident::TYPE_QR_INSUFFICIENT_FUNDS,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error');

        $this->assertSame(0, LimoIncident::query()->count());
    }

    public function test_from_plate_upload_validation_returns_errors_key(): void
    {
        Storage::fake('local');
        $admin = $this->makeLimoAdmin('limo_inc_val_json');
        $upload = $this->makePlateUpload($admin, [
            'upload_token' => 'upltok_val'.str_repeat('F', 35),
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/incident/from-plate-upload', [
                'upload_token' => $upload->upload_token,
                'type' => 'invalid_type_value',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['type']]);
    }

    public function test_limo_access_user_can_create_qr_insufficient_funds_incident(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = $this->makeLimoAdmin('limo_inc_b');

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $plate = UploadedFile::fake()->image('plate.jpg', 400, 300);

        $response = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident', [
                'type' => LimoIncident::TYPE_QR_INSUFFICIENT_FUNDS,
                'license_plate' => 'PG-TEST-1',
                'plate_photo' => $plate,
                'note' => 'Napomena',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('communal_email_sent', true);

        $uuid = $response->json('incident_uuid');
        $this->assertNotEmpty($uuid);

        $this->assertDatabaseHas('limo_incidents', [
            'incident_uuid' => $uuid,
            'type' => LimoIncident::TYPE_QR_INSUFFICIENT_FUNDS,
            'license_plate_snapshot' => 'PGTEST1',
            'recorded_by_limo_admin_id' => $admin->id,
        ]);

        $incident = LimoIncident::query()->where('incident_uuid', $uuid)->first();
        $this->assertNotNull($incident);
        Storage::disk('local')->assertExists($incident->plate_photo_path);
        $this->assertNotNull($incident->communal_email_sent_at);
        $this->assertNotNull($incident->admin_alert_id);

        $alert = AdminAlert::query()->find($incident->admin_alert_id);
        $this->assertNotNull($alert);
        $this->assertSame('limo_incident', $alert->type);
        $this->assertSame($uuid, $alert->payload_json['incident_uuid'] ?? null);

        Mail::assertSent(LimoCommunalPoliceIncidentMail::class, function (LimoCommunalPoliceIncidentMail $mail) use ($uuid) {
            return $mail->incident->incident_uuid === $uuid
                && $mail->hasTo('komunalna.policija@kotor.me');
        });
    }

    public function test_email_failure_does_not_rollback_incident(): void
    {
        Storage::fake('local');

        $realManager = $this->app->make('mail.manager');
        $failingFake = new class($realManager) extends MailFake
        {
            protected function sendMail($view, $shouldQueue = false): void
            {
                throw new RuntimeException('simulated mail failure');
            }
        };
        Mail::swap($failingFake);

        $admin = $this->makeLimoAdmin('limo_inc_c');

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident', [
                'type' => LimoIncident::TYPE_INVALID_QR_TOKEN,
                'plate_photo' => UploadedFile::fake()->image('p.jpg', 200, 200),
            ]);

        $response->assertOk()->assertJsonPath('communal_email_sent', false);

        $uuid = $response->json('incident_uuid');
        $incident = LimoIncident::query()->where('incident_uuid', $uuid)->first();
        $this->assertNotNull($incident);
        $this->assertNull($incident->communal_email_sent_at);
        $this->assertNotNull($incident->admin_alert_id);

        $alert = AdminAlert::query()->find($incident->admin_alert_id);
        $this->assertNotNull($alert);
        $this->assertFalse((bool) ($alert->payload_json['communal_email_sent'] ?? true));
    }

    public function test_unregistered_vehicle_with_branding_stores_visible_agency_and_branding_photo(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = $this->makeLimoAdmin('limo_inc_d');

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident', [
                'type' => LimoIncident::TYPE_UNREGISTERED_VEHICLE_WITH_BRANDING,
                'visible_agency_name' => 'Test Agency DOO',
                'plate_photo' => UploadedFile::fake()->image('pl.jpg', 200, 200),
                'branding_photo' => UploadedFile::fake()->image('br.jpg', 200, 200),
            ]);

        $response->assertOk();
        $uuid = $response->json('incident_uuid');

        $incident = LimoIncident::query()->where('incident_uuid', $uuid)->first();
        $this->assertSame('Test Agency DOO', $incident->visible_agency_name);
        $this->assertNotNull($incident->branding_photo_path);
        Storage::disk('local')->assertExists($incident->branding_photo_path);
    }

    public function test_user_without_limo_access_cannot_create_incident(): void
    {
        Storage::fake('local');

        $admin = Admin::query()->create([
            'username' => 'no_limo',
            'email' => 'no_limo@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => false,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident', [
                'type' => LimoIncident::TYPE_DRIVER_NON_COOPERATIVE,
                'plate_photo' => UploadedFile::fake()->image('x.jpg', 100, 100),
            ])
            ->assertForbidden();

        $this->assertSame(0, LimoIncident::query()->count());
    }

    public function test_invalid_type_rejected(): void
    {
        Storage::fake('local');
        $admin = $this->makeLimoAdmin('limo_inc_e');

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident', [
                'type' => 'not_a_real_type',
                'plate_photo' => UploadedFile::fake()->image('x.jpg', 100, 100),
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_incident_does_not_create_limo_pickup_event(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = $this->makeLimoAdmin('limo_inc_f');

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $before = LimoPickupEvent::query()->count();

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident', [
                'type' => LimoIncident::TYPE_PLATE_INSUFFICIENT_FUNDS,
                'plate_photo' => UploadedFile::fake()->image('x.jpg', 100, 100),
            ])
            ->assertOk();

        $this->assertSame($before, LimoPickupEvent::query()->count());
    }

    public function test_incident_does_not_create_agency_advance_transaction(): void
    {
        Storage::fake('local');
        Mail::fake();

        $agency = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '50.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'topup',
        ]);

        $admin = $this->makeLimoAdmin('limo_inc_g');

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $before = AgencyAdvanceTransaction::query()->count();

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident', [
                'type' => LimoIncident::TYPE_QR_INSUFFICIENT_FUNDS,
                'agency_user_id' => $agency->id,
                'plate_photo' => UploadedFile::fake()->image('x.jpg', 100, 100),
            ])
            ->assertOk();

        $this->assertSame($before, AgencyAdvanceTransaction::query()->count());
    }

    public function test_creates_incident_from_existing_limo_plate_upload_without_new_plate_photo(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = $this->makeLimoAdmin('limo_inc_from_upl_a');
        $upload = $this->makePlateUpload($admin, [
            'upload_token' => 'upltok_'.str_repeat('B', 40),
            'gps_lat' => '42.1111111',
            'gps_lng' => '18.2222222',
            'device_info' => '{"device":"x"}',
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $beforeEvents = LimoPickupEvent::query()->count();
        $beforeAdv = AgencyAdvanceTransaction::query()->count();

        $res = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident/from-plate-upload', [
                'upload_token' => $upload->upload_token,
                'type' => LimoIncident::TYPE_PLATE_INSUFFICIENT_FUNDS,
                'license_plate' => 'PG-TEST-2',
                'note' => 'note',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $uuid = (string) $res->json('incident_uuid');
        $this->assertNotSame('', $uuid);

        $incident = LimoIncident::query()->where('incident_uuid', $uuid)->first();
        $this->assertNotNull($incident);
        $this->assertSame('PGTEST2', $incident->license_plate_snapshot);
        $this->assertSame((string) $upload->gps_lat, (string) $incident->gps_lat);
        $this->assertSame((string) $upload->gps_lng, (string) $incident->gps_lng);
        $this->assertSame($upload->device_info, $incident->device_info);

        Storage::disk('local')->assertExists($incident->plate_photo_path);
        Storage::disk('local')->assertExists($upload->path);
        $this->assertNotSame($upload->path, $incident->plate_photo_path);
        $this->assertSame(Storage::disk('local')->get($upload->path), Storage::disk('local')->get($incident->plate_photo_path));

        $this->assertSame($beforeEvents, LimoPickupEvent::query()->count());
        $this->assertSame($beforeAdv, AgencyAdvanceTransaction::query()->count());

        $uploadFresh = LimoPlateUpload::query()->where('id', $upload->id)->first();
        $this->assertNotNull($uploadFresh);
        $this->assertNull($uploadFresh->consumed_at);
    }

    public function test_upload_token_owned_by_another_evidenter_is_rejected_for_incident_from_upload(): void
    {
        Storage::fake('local');
        Mail::fake();

        $adminA = $this->makeLimoAdmin('limo_inc_from_upl_owner_a');
        $adminB = $this->makeLimoAdmin('limo_inc_from_upl_owner_b');
        $upload = $this->makePlateUpload($adminA, ['upload_token' => 'upltok_'.str_repeat('C', 40)]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($adminB, 'panel_admin')
            ->post('/limo/incident/from-plate-upload', [
                'upload_token' => $upload->upload_token,
                'type' => LimoIncident::TYPE_QR_INSUFFICIENT_FUNDS,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_upload');
    }

    public function test_expired_upload_is_rejected_for_incident_from_upload(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = $this->makeLimoAdmin('limo_inc_from_upl_exp');
        $upload = $this->makePlateUpload($admin, [
            'upload_token' => 'upltok_'.str_repeat('D', 40),
            'expires_at' => now()->subMinute(),
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident/from-plate-upload', [
                'upload_token' => $upload->upload_token,
                'type' => LimoIncident::TYPE_QR_INSUFFICIENT_FUNDS,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_upload');
    }

    public function test_missing_upload_file_is_rejected_for_incident_from_upload(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = $this->makeLimoAdmin('limo_inc_from_upl_missing');
        $upload = LimoPlateUpload::query()->create([
            'upload_token' => 'upltok_'.str_repeat('E', 40),
            'path' => 'limo_plate_uploads/missing.jpg',
            'ocr_text' => null,
            'gps_lat' => '42.0000000',
            'gps_lng' => '18.0000000',
            'device_info' => '{}',
            'uploaded_by_limo_admin_id' => $admin->id,
            'expires_at' => now()->addHour(),
            'consumed_at' => null,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident/from-plate-upload', [
                'upload_token' => $upload->upload_token,
                'type' => LimoIncident::TYPE_QR_INSUFFICIENT_FUNDS,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_upload');
    }

    public function test_incident_email_targets_fallback_komunalna_when_no_limo_settings(): void
    {
        Storage::fake('local');
        Mail::fake();

        $admin = $this->makeLimoAdmin('limo_inc_fb');

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident', [
                'type' => LimoIncident::TYPE_INVALID_QR_TOKEN,
                'plate_photo' => UploadedFile::fake()->image('pf.jpg', 200, 200),
            ])
            ->assertOk()
            ->assertJsonPath('communal_email_sent', true);

        Mail::assertSent(LimoCommunalPoliceIncidentMail::class, function (LimoCommunalPoliceIncidentMail $mail) {
            return $mail->hasTo('komunalna.policija@kotor.me');
        });
    }

    public function test_incident_email_targets_configured_limo_recipients(): void
    {
        Storage::fake('local');
        Mail::fake();

        ReportEmail::query()->create([
            'email' => 'incident-recipient@example.com',
            'purpose' => ReportEmail::PURPOSE_LIMO_INCIDENTS,
        ]);

        $admin = $this->makeLimoAdmin('limo_inc_cfg');

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident', [
                'type' => LimoIncident::TYPE_INVALID_QR_TOKEN,
                'plate_photo' => UploadedFile::fake()->image('pc.jpg', 200, 200),
            ])
            ->assertOk()
            ->assertJsonPath('communal_email_sent', true);

        Mail::assertSent(LimoCommunalPoliceIncidentMail::class, function (LimoCommunalPoliceIncidentMail $mail) {
            return $mail->hasTo('incident-recipient@example.com')
                && ! $mail->hasTo('komunalna.policija@kotor.me');
        });
    }

    public function test_incident_email_targets_all_configured_limo_recipients(): void
    {
        Storage::fake('local');
        Mail::fake();

        ReportEmail::query()->create([
            'email' => 'inc-a@example.com',
            'purpose' => ReportEmail::PURPOSE_LIMO_INCIDENTS,
        ]);
        ReportEmail::query()->create([
            'email' => 'inc-b@example.com',
            'purpose' => ReportEmail::PURPOSE_LIMO_INCIDENTS,
        ]);

        $admin = $this->makeLimoAdmin('limo_inc_many');

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident', [
                'type' => LimoIncident::TYPE_INVALID_QR_TOKEN,
                'plate_photo' => UploadedFile::fake()->image('pm.jpg', 200, 200),
            ])
            ->assertOk();

        Mail::assertSent(LimoCommunalPoliceIncidentMail::class, function (LimoCommunalPoliceIncidentMail $mail) {
            return $mail->hasTo('inc-a@example.com') && $mail->hasTo('inc-b@example.com');
        });
    }

    public function test_incident_email_does_not_target_scheduled_report_recipients(): void
    {
        Storage::fake('local');
        Mail::fake();

        ReportEmail::query()->create([
            'email' => 'scheduled-report-only@example.com',
            'purpose' => ReportEmail::PURPOSE_REPORT,
        ]);

        $admin = $this->makeLimoAdmin('limo_inc_rep');

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/incident', [
                'type' => LimoIncident::TYPE_INVALID_QR_TOKEN,
                'plate_photo' => UploadedFile::fake()->image('pr.jpg', 200, 200),
            ])
            ->assertOk();

        Mail::assertSent(LimoCommunalPoliceIncidentMail::class, function (LimoCommunalPoliceIncidentMail $mail) {
            return $mail->hasTo('komunalna.policija@kotor.me')
                && ! $mail->hasTo('scheduled-report-only@example.com');
        });
    }
}
