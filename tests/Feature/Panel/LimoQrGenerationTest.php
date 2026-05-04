<?php

namespace Tests\Feature\Panel;

use App\Models\Admin;
use App\Models\AgencyAdvanceTransaction;
use App\Models\LimoPickupEvent;
use App\Models\LimoQrToken;
use App\Models\User;
use App\Services\Limo\LimoPickupService;
use App\Services\Limo\LimoQrService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LimoQrGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-10 12:00:00', 'Europe/Podgorica'));
        config(['features.advance_payments' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generate_token_success_creates_row_and_flashes_raw_once(): void
    {
        $user = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'test',
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->actingAs($user)
            ->post(route('panel.limo.qr.generate'));

        $response->assertRedirect(route('panel.limo.index'))
            ->assertSessionHas('limo_new_qr_token');

        $raw = session('limo_new_qr_token');
        $this->assertIsString($raw);
        $this->assertSame(64, strlen($raw));

        $this->assertDatabaseCount('limo_qr_tokens', 1);
        $this->assertDatabaseHas('limo_qr_tokens', [
            'agency_user_id' => $user->id,
            'token_hash' => LimoPickupService::hashToken($raw),
        ]);

        $row = LimoQrToken::query()->first();
        $this->assertNotNull($row);
        $this->assertNotEmpty($row->encrypted_token);
    }

    public function test_daily_limit_returns_error_after_twentieth_slot(): void
    {
        $user = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '500.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'test',
        ]);

        $svc = app(LimoQrService::class);
        for ($i = 0; $i < 20; $i++) {
            $svc->generateForAgency($user->id);
        }

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($user)
            ->post(route('panel.limo.qr.generate'))
            ->assertRedirect(route('panel.limo.index'))
            ->assertSessionHasErrors('generate');

        $this->assertDatabaseCount('limo_qr_tokens', 20);
    }

    public function test_insufficient_advance_returns_error(): void
    {
        $user = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '10.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'test',
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($user)
            ->post(route('panel.limo.qr.generate'))
            ->assertRedirect(route('panel.limo.index'))
            ->assertSessionHasErrors('generate');

        $this->assertDatabaseCount('limo_qr_tokens', 0);
    }

    public function test_user_sees_only_own_tokens(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        foreach ([$userA, $userB] as $u) {
            AgencyAdvanceTransaction::query()->create([
                'agency_user_id' => $u->id,
                'amount' => '100.00',
                'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
                'reference_type' => null,
                'reference_id' => null,
                'merchant_transaction_id' => null,
                'note' => 'test',
            ]);
        }

        app(LimoQrService::class)->generateForAgency($userA->id);

        $this->actingAs($userB)
            ->get(route('panel.limo.index'))
            ->assertOk()
            ->assertViewHas('tokens', fn ($tokens) => $tokens->count() === 0);

        $this->actingAs($userA)
            ->get(route('panel.limo.index'))
            ->assertOk()
            ->assertViewHas('tokens', fn ($tokens) => $tokens->count() === 1);
    }

    public function test_used_token_not_listed_after_simulated_pickup(): void
    {
        $user = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'test',
        ]);

        $admin = Admin::query()->create([
            'username' => 'pickup_admin',
            'email' => 'pickup-admin@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => false,
        ]);

        $tokenRow = app(LimoQrService::class)->generateForAgency($user->id)['token'];
        $hash = $tokenRow->token_hash;
        $tokenRow->delete();

        LimoPickupEvent::query()->create([
            'merchant_transaction_id' => (string) Str::uuid(),
            'agency_user_id' => $user->id,
            'agency_name_snapshot' => $user->name,
            'agency_email_snapshot' => $user->email,
            'agency_country_snapshot' => $user->country,
            'source' => 'qr',
            'qr_token_hash' => $hash,
            'qr_valid_on' => Carbon::today('Europe/Podgorica'),
            'license_plate_snapshot' => null,
            'amount_snapshot' => '15.00',
            'service_name_snapshot' => LimoPickupService::SERVICE_NAME,
            'occurred_at' => now(),
            'recorded_by_limo_admin_id' => $admin->id,
            'device_info' => null,
            'status' => 'pending_fiscal',
        ]);

        $this->actingAs($user)
            ->get(route('panel.limo.index'))
            ->assertOk()
            ->assertViewHas('tokens', fn ($tokens) => $tokens->count() === 0);
    }

    public function test_advance_feature_off_returns_404(): void
    {
        config(['features.advance_payments' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('panel.limo.index'))
            ->assertNotFound();
    }
}
