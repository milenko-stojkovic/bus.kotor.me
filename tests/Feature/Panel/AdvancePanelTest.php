<?php

namespace Tests\Feature\Panel;

use App\Models\AgencyAdvanceTopup;
use App\Models\AgencyAdvanceTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AdvancePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_false_blocks_routes_and_does_not_create_topup_and_nav_is_disabled(): void
    {
        config(['features.advance_payments' => false]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // Nav shows label but no link.
        $html = $this->get(route('panel.reservations', [], false))->assertOk()->getContent();
        $this->assertStringContainsString('Avans', $html);
        $this->assertStringNotContainsString('/panel/avans', $html);

        $this->get(route('panel.advance.index', [], false))->assertNotFound();

        $this->post(route('panel.advance.topup.store', [], false), ['amount' => '100'])
            ->assertNotFound();

        $this->assertSame(0, AgencyAdvanceTopup::query()->count());
    }

    public function test_feature_flag_true_shows_balance_and_ledger_and_valid_amount_creates_pending_topup_without_ledger(): void
    {
        config(['features.advance_payments' => true]);
        config(['services.bank.driver' => 'bankart']);
        config([
            'services.bankart.api_url' => 'https://bankart.test',
            'services.bankart.api_key' => 'k',
            'services.bankart.username' => 'u',
            'services.bankart.password' => 'p',
            'services.bankart.shared_secret' => 's',
            'services.bankart.signature_enabled' => false,
            'services.bankart.send_customer' => false,
        ]);

        Http::fake([
            'https://bankart.test/*' => Http::response(['redirectUrl' => 'https://gateway.test/pay'], 200),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'note' => 't1',
        ]);
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '-30.00',
            'type' => AgencyAdvanceTransaction::TYPE_USAGE,
            'note' => 'u1',
        ]);
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '-5.00',
            'type' => AgencyAdvanceTransaction::TYPE_CORRECTION,
            'note' => 'c1',
        ]);

        $this->get(route('panel.advance.index', [], false))
            ->assertOk()
            ->assertSee('65.00 EUR', false)
            ->assertSee('t1', false)
            ->assertSee('u1', false);

        $beforeLedger = AgencyAdvanceTransaction::query()->count();

        $this->post(route('panel.advance.topup.store', [], false), ['amount' => '105.00'])
            ->assertRedirect('https://gateway.test/pay');

        $this->assertSame(1, AgencyAdvanceTopup::query()->count());
        $topup = AgencyAdvanceTopup::query()->firstOrFail();
        $this->assertSame($user->id, (int) $topup->agency_user_id);
        $this->assertSame('105.00', (string) $topup->amount);
        $this->assertSame(AgencyAdvanceTopup::STATUS_PENDING, (string) $topup->status);

        // Must not create ledger row at topup start.
        $this->assertSame($beforeLedger, AgencyAdvanceTransaction::query()->count());
    }

    public function test_invalid_amounts_are_rejected(): void
    {
        config(['features.advance_payments' => true]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        foreach (['100.50', '102', '0', '-10'] as $bad) {
            $this->from(route('panel.advance.index', [], false))
                ->post(route('panel.advance.topup.store', [], false), ['amount' => $bad])
                ->assertRedirect(route('panel.advance.index', [], false))
                ->assertSessionHasErrors('amount');
        }

        $this->assertSame(0, AgencyAdvanceTopup::query()->count());
    }

    public function test_isolation_agency_sees_only_own_ledger(): void
    {
        config(['features.advance_payments' => true]);

        $a = User::factory()->create(['email_verified_at' => now()]);
        $b = User::factory()->create(['email_verified_at' => now()]);

        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $a->id,
            'amount' => '50.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'note' => 'a_only',
        ]);
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $b->id,
            'amount' => '70.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'note' => 'b_only',
        ]);

        $this->actingAs($a);
        $this->get(route('panel.advance.index', [], false))
            ->assertOk()
            ->assertSee('a_only', false)
            ->assertDontSee('b_only', false);
    }
}

