<?php

namespace Tests\Feature\Limo;

use App\Jobs\ProcessLimoAfterPaymentJob;
use App\Models\Admin;
use App\Models\AgencyAdvanceTransaction;
use App\Models\LimoPickupEvent;
use App\Models\LimoPickupPhoto;
use App\Models\LimoPlateUpload;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Services\Limo\LimoOcrRunner;

class LimoPlateFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-04 12:00:00', 'Europe/Podgorica'));
        config(['limo.ocr.enabled' => false]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_upload_plate_photo_returns_upload_token(): void
    {
        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_up');

        $file = UploadedFile::fake()->image('plate.jpg', 640, 480);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $response = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => $file,
                'device_info' => '{"t":1}',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['upload_token', 'suggested_plate']);

        $data = $response->json();
        $this->assertNotEmpty($data['upload_token']);
        $this->assertNull($data['suggested_plate']);

        $this->assertSame(1, LimoPlateUpload::query()->count());
    }

    public function test_ocr_disabled_upload_still_ok_and_suggestion_null(): void
    {
        config(['limo.ocr.enabled' => false]);
        Storage::fake('local');

        $admin = $this->makeLimoAdmin('plate_ocr_off');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('suggested_plate', null);
    }

    public function test_ocr_noisy_text_suggests_normalized_plate(): void
    {
        config(['limo.ocr.enabled' => true]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds): string
            {
                return "xx\\nPG 123-AB\\nnoise";
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_ocr_ok');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('suggested_plate', 'PG123AB');
    }

    public function test_ocr_failure_does_not_break_upload_flow(): void
    {
        config(['limo.ocr.enabled' => true]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds): string
            {
                throw new \RuntimeException('tesseract timeout');
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_ocr_fail');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('suggested_plate', null);
    }

    public function test_confirm_registered_plate_with_advance_creates_event_attaches_photo_and_dispatches_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->makeLimoAdmin('plate_ok');
        $agency = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'topup',
        ]);
        $vt = VehicleType::query()->create(['price' => 12]);
        Vehicle::query()->create([
            'user_id' => $agency->id,
            'license_plate' => 'PGTEST99',
            'vehicle_type_id' => $vt->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $upload = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('x.jpg', 400, 300),
            ])
            ->assertOk()
            ->json();

        $confirm = $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/plate/confirm', [
                'upload_token' => $upload['upload_token'],
                'license_plate' => 'PG-TEST-99',
                'gps_lat' => 42.2,
                'gps_lng' => 18.7,
                'device_info' => '{}',
            ])
            ->assertOk()
            ->json();

        $this->assertSame('ok', $confirm['status']);
        $this->assertArrayHasKey('merchant_transaction_id', $confirm);

        $event = LimoPickupEvent::query()->where('license_plate_snapshot', 'PGTEST99')->first();
        $this->assertNotNull($event);
        $this->assertSame('plate', $event->source);
        $this->assertSame('pending_fiscal', $event->status);

        $this->assertSame(1, LimoPickupPhoto::query()->where('limo_pickup_event_id', $event->id)->where('type', 'plate')->count());

        $this->assertDatabaseHas('agency_advance_transactions', [
            'agency_user_id' => $agency->id,
            'type' => AgencyAdvanceTransaction::TYPE_USAGE,
            'amount' => '-15.00',
        ]);

        Queue::assertPushed(ProcessLimoAfterPaymentJob::class, fn (ProcessLimoAfterPaymentJob $job) => $job->limoPickupEventId === $event->id);

        $this->assertNotNull(LimoPlateUpload::query()->where('upload_token', $upload['upload_token'])->value('consumed_at'));
    }

    public function test_unregistered_plate_returns_plate_not_registered(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->makeLimoAdmin('plate_nr');

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $upload = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('x.jpg', 200, 200),
            ])
            ->assertOk()
            ->json();

        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/plate/confirm', [
                'upload_token' => $upload['upload_token'],
                'license_plate' => 'ZZ999ZZ',
            ])
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'code' => 'plate_not_registered',
            ]);

        $this->assertSame(0, LimoPickupEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_insufficient_advance_returns_error_without_event(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->makeLimoAdmin('plate_low');
        $agency = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '10.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'topup',
        ]);
        $vt = VehicleType::query()->create(['price' => 12]);
        Vehicle::query()->create([
            'user_id' => $agency->id,
            'license_plate' => 'LOWBAL01',
            'vehicle_type_id' => $vt->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $upload = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('x.jpg', 200, 200),
            ])
            ->assertOk()
            ->json();

        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/plate/confirm', [
                'upload_token' => $upload['upload_token'],
                'license_plate' => 'LOWBAL01',
            ])
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'code' => 'insufficient_advance',
            ]);

        $this->assertSame(0, LimoPickupEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_upload_token_cannot_be_reused(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->makeLimoAdmin('plate_reuse');
        $agency = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'topup',
        ]);
        $vt = VehicleType::query()->create(['price' => 12]);
        Vehicle::query()->create([
            'user_id' => $agency->id,
            'license_plate' => 'REUSE01',
            'vehicle_type_id' => $vt->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $upload = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('x.jpg', 200, 200),
            ])
            ->json();

        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/plate/confirm', [
                'upload_token' => $upload['upload_token'],
                'license_plate' => 'REUSE01',
            ])
            ->assertOk();

        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/plate/confirm', [
                'upload_token' => $upload['upload_token'],
                'license_plate' => 'REUSE01',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_upload');
    }

    public function test_another_evidenter_cannot_consume_foreign_upload_token(): void
    {
        Storage::fake('local');
        Queue::fake();

        $adminA = $this->makeLimoAdmin('plate_a');
        $adminB = $this->makeLimoAdmin('plate_b');

        $agency = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'topup',
        ]);
        $vt = VehicleType::query()->create(['price' => 12]);
        Vehicle::query()->create([
            'user_id' => $agency->id,
            'license_plate' => 'OTHER01',
            'vehicle_type_id' => $vt->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $upload = $this->actingAs($adminA, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('x.jpg', 200, 200),
            ])
            ->assertOk()
            ->json();

        $this->actingAs($adminB, 'panel_admin')
            ->postJson('/limo/pickup/plate/confirm', [
                'upload_token' => $upload['upload_token'],
                'license_plate' => 'OTHER01',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_upload');

        $this->assertSame(0, LimoPickupEvent::query()->count());
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
}
