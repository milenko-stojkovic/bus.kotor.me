<?php

namespace Tests\Feature\Limo;

use App\Jobs\ProcessLimoAfterPaymentJob;
use App\Models\Admin;
use App\Models\AgencyAdvanceTransaction;
use App\Models\LimoPickupEvent;
use App\Models\LimoQrToken;
use App\Models\User;
use App\Services\Limo\LimoPickupService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LimoPickupQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-04 12:00:00', 'Europe/Podgorica'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_valid_qr_deducts_advance_and_creates_event(): void
    {
        Queue::fake();
        $admin = Admin::query()->create([
            'username' => 'limo_admin',
            'email' => 'limo-admin@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);
        $agency = User::factory()->create(['name' => 'Agency X', 'email' => 'agency@example.com', 'country' => 'ME']);
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'test topup',
        ]);
        $raw = 'secret-qr-payload';
        LimoQrToken::query()->create([
            'agency_user_id' => $agency->id,
            'token_hash' => LimoPickupService::hashToken($raw),
            'valid_on' => Carbon::today('Europe/Podgorica'),
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $response = $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/qr', ['token' => $raw]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('remaining_balance', '85.00')
            ->assertJsonStructure(['merchant_transaction_id']);

        $this->assertDatabaseHas('limo_pickup_events', [
            'agency_user_id' => $agency->id,
            'source' => 'qr',
            'status' => 'pending_fiscal',
            'recorded_by_limo_admin_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('agency_advance_transactions', [
            'agency_user_id' => $agency->id,
            'type' => AgencyAdvanceTransaction::TYPE_USAGE,
            'reference_type' => 'limo_pickup_event',
            'amount' => '-15.00',
        ]);
        $this->assertDatabaseCount('limo_qr_tokens', 0);

        $event = LimoPickupEvent::query()->where('agency_user_id', $agency->id)->first();
        $this->assertNotNull($event);
        Queue::assertPushed(ProcessLimoAfterPaymentJob::class, function (ProcessLimoAfterPaymentJob $job) use ($event) {
            return $job->limoPickupEventId === $event->id;
        });
    }

    public function test_invalid_token_returns_error_and_no_event(): void
    {
        Queue::fake();
        $admin = Admin::query()->create([
            'username' => 'limo_admin2',
            'email' => 'limo-admin2@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $response = $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/qr', ['token' => 'unknown']);

        $response->assertStatus(422)->assertJson([
            'status' => 'error',
            'code' => 'invalid_token',
        ]);
        $this->assertSame(0, LimoPickupEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_insufficient_advance_returns_error_and_no_event(): void
    {
        Queue::fake();
        $admin = Admin::query()->create([
            'username' => 'limo_admin3',
            'email' => 'limo-admin3@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);
        $agency = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '10.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'test topup',
        ]);
        $raw = 'qr-low-balance';
        LimoQrToken::query()->create([
            'agency_user_id' => $agency->id,
            'token_hash' => LimoPickupService::hashToken($raw),
            'valid_on' => Carbon::today('Europe/Podgorica'),
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $response = $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/qr', ['token' => $raw]);

        $response->assertStatus(422)->assertJson([
            'status' => 'error',
            'code' => 'insufficient_advance',
        ]);
        $this->assertSame(0, LimoPickupEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_reused_token_returns_error(): void
    {
        Queue::fake();
        $admin = Admin::query()->create([
            'username' => 'limo_admin4',
            'email' => 'limo-admin4@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);
        $agency = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'test topup',
        ]);
        $raw = 'reuse-me';
        LimoQrToken::query()->create([
            'agency_user_id' => $agency->id,
            'token_hash' => LimoPickupService::hashToken($raw),
            'valid_on' => Carbon::today('Europe/Podgorica'),
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/qr', ['token' => $raw])
            ->assertOk();

        $second = $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/qr', ['token' => $raw]);

        $second->assertStatus(422)->assertJson([
            'status' => 'error',
            'code' => 'token_already_used',
        ]);
        $this->assertSame(1, LimoPickupEvent::query()->count());
    }

    public function test_panel_admin_without_limo_access_cannot_post_pickup_qr(): void
    {
        $admin = Admin::query()->create([
            'username' => 'no_limo',
            'email' => 'no-limo@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => false,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/qr', ['token' => 'x'])
            ->assertForbidden();
    }

    public function test_limo_only_admin_with_limo_access_can_complete_pickup(): void
    {
        Queue::fake();
        $admin = Admin::query()->create([
            'username' => 'limo_only',
            'email' => 'limo-only@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => false,
            'limo_access' => true,
        ]);
        $agency = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'test topup',
        ]);
        $raw = 'limo-only-qr';
        LimoQrToken::query()->create([
            'agency_user_id' => $agency->id,
            'token_hash' => LimoPickupService::hashToken($raw),
            'valid_on' => Carbon::today('Europe/Podgorica'),
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/qr', ['token' => $raw])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertDatabaseHas('limo_pickup_events', [
            'agency_user_id' => $agency->id,
            'recorded_by_limo_admin_id' => $admin->id,
        ]);
    }
}
