<?php

namespace Tests\Feature\Limo;

use App\Models\Admin;
use App\Models\AgencyAdvanceTransaction;
use App\Models\LimoQrToken;
use App\Models\User;
use App\Services\Limo\LimoPickupService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LimoEntryUiTest extends TestCase
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

    public function test_guest_cannot_access_limo_entry(): void
    {
        $this->get('/limo')
            ->assertRedirect(route('panel_admin.login'));
    }

    public function test_panel_admin_without_limo_access_gets_403(): void
    {
        $admin = Admin::query()->create([
            'username' => 'no_limo_ui',
            'email' => 'no-limo-ui@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => false,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get('/limo')
            ->assertForbidden();
    }

    public function test_limo_access_user_can_access_limo_and_sees_title(): void
    {
        $admin = Admin::query()->create([
            'username' => 'limo_ui_ok',
            'email' => 'limo-ui@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => false,
            'limo_access' => true,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get('/limo')
            ->assertOk()
            ->assertSee('Limo evidencija');
    }

    public function test_limo_health_requires_limo_access(): void
    {
        $admin = Admin::query()->create([
            'username' => 'no_limo_health',
            'email' => 'no-limo-health@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => false,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get('/limo/health')
            ->assertForbidden();
    }

    public function test_limo_health_returns_json_for_limo_access_user(): void
    {
        $admin = Admin::query()->create([
            'username' => 'limo_health_ok',
            'email' => 'limo-health@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => false,
            'limo_access' => true,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->getJson('/limo/health')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'scope' => 'limo']);
    }

    public function test_post_pickup_qr_returns_json_success_for_valid_token(): void
    {
        Queue::fake();
        $admin = Admin::query()->create([
            'username' => 'limo_ui_post_ok',
            'email' => 'limo-ui-post@example.com',
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
        $raw = 'entry-ui-valid-token';
        LimoQrToken::query()->create([
            'agency_user_id' => $agency->id,
            'token_hash' => LimoPickupService::hashToken($raw),
            'valid_on' => Carbon::today('Europe/Podgorica'),
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/qr', [
                'token' => $raw,
                'device_info' => '{"test":true}',
                'gps_lat' => 42.43,
                'gps_lng' => 18.77,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['merchant_transaction_id', 'remaining_balance']);
    }

    public function test_post_pickup_qr_returns_json_error_for_invalid_token(): void
    {
        $admin = Admin::query()->create([
            'username' => 'limo_ui_post_bad',
            'email' => 'limo-ui-bad@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/qr', ['token' => 'totally-unknown-token'])
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'code' => 'invalid_token',
            ]);
    }
}
