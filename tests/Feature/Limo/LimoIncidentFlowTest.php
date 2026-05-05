<?php

namespace Tests\Feature\Limo;

use App\Mail\LimoCommunalPoliceIncidentMail;
use App\Models\Admin;
use App\Models\AdminAlert;
use App\Models\AgencyAdvanceTransaction;
use App\Models\LimoIncident;
use App\Models\LimoPickupEvent;
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
            return $mail->incident->incident_uuid === $uuid;
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
}
