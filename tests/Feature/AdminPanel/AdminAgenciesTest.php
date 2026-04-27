<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use App\Models\AgencyAdvanceTopup;
use App\Models\AgencyAdvanceTransaction;
use App\Mail\AdvanceTopupConfirmationMail;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class AdminAgenciesTest extends TestCase
{
    use RefreshDatabase;

    private function seedAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'agadmin',
            'email' => 'ag-admin@example.com',
            'password' => bcrypt('secret-password-ag'),
            'control_access' => false,
            'admin_access' => true,
        ]);
    }

    public function test_agencies_list_renders_and_shows_balances(): void
    {
        config(['features.advance_payments' => true]);

        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $u1 = User::factory()->create(['name' => 'Alpha', 'email' => 'a@example.com']);
        $u2 = User::factory()->create(['name' => 'Beta', 'email' => 'b@example.com']);

        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $u1->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
        ]);
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $u2->id,
            'amount' => '55.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
        ]);

        $this->get(route('panel_admin.agencies.index', [], false))
            ->assertOk()
            ->assertSee('Agencije', false)
            ->assertSee('Alpha', false)
            ->assertSee('Beta', false)
            ->assertSee('100.00 EUR', false)
            ->assertSee('55.00 EUR', false);
    }

    public function test_agency_detail_renders_basic_and_ledger_and_topups_when_flag_on(): void
    {
        config(['features.advance_payments' => true]);

        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $u = User::factory()->create(['name' => 'Gamma', 'email' => 'g@example.com']);

        $slot = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $vt = VehicleType::query()->create(['price' => 10]);

        $res = Reservation::query()->create([
            'user_id' => $u->id,
            'vehicle_id' => null,
            'merchant_transaction_id' => 'mtid_r1',
            'payment_method' => 'advance',
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => now()->addDays(3)->toDateString(),
            'user_name' => 'Gamma',
            'country' => 'ME',
            'license_plate' => 'KO111AA',
            'vehicle_type_id' => $vt->id,
            'email' => 'g@example.com',
            'preferred_locale' => 'cg',
            'status' => 'paid',
            'invoice_amount' => '10.00',
            'email_sent' => 0,
            'created_by_admin' => false,
        ]);

        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $u->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'merchant_transaction_id' => 'mtid_topup',
            'reference_type' => 'advance_topup',
            'reference_id' => 10,
            'note' => 't1',
        ]);
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $u->id,
            'amount' => '-10.00',
            'type' => AgencyAdvanceTransaction::TYPE_USAGE,
            'merchant_transaction_id' => 'mtid_r1',
            'reference_type' => 'reservation',
            'reference_id' => $res->id,
            'note' => 'u1',
        ]);

        AgencyAdvanceTopup::query()->create([
            'agency_user_id' => $u->id,
            'merchant_transaction_id' => 'mtid_topup',
            'amount' => '100.00',
            'status' => AgencyAdvanceTopup::STATUS_PAID,
        ]);

        $this->get(route('panel_admin.agencies.show', $u, false))
            ->assertOk()
            ->assertSee('Agencija: Gamma', false)
            ->assertSee('Ledger istorija', false)
            ->assertSee('Topup istorija', false)
            ->assertSee('t1', false)
            ->assertSee('u1', false)
            ->assertSee('mtid_topup', false);
    }

    public function test_feature_flag_off_hides_advance_section_placeholder(): void
    {
        config(['features.advance_payments' => false]);

        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $u = User::factory()->create(['name' => 'Delta']);

        $this->get(route('panel_admin.agencies.show', $u, false))
            ->assertOk()
            ->assertSee('Avansna funkcionalnost trenutno nije aktivna.', false)
            ->assertDontSee('Ledger istorija', false)
            ->assertDontSee('Topup istorija', false);
    }

    public function test_feature_flag_off_blocks_correction_route_and_does_not_change_ledger(): void
    {
        config(['features.advance_payments' => false]);

        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $u = User::factory()->create(['name' => 'NoAdvance']);

        $this->post(route('panel_admin.agencies.advance.correction.store', $u, false), [
            'amount' => 10,
            'direction' => 'increase',
            'reason' => 'test reason',
        ])->assertNotFound();

        $this->assertSame(0, AgencyAdvanceTransaction::query()->count());
    }

    public function test_admin_can_add_positive_and_negative_corrections_and_negative_cannot_go_below_zero(): void
    {
        config(['features.advance_payments' => true]);

        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $u = User::factory()->create(['name' => 'Corr']);

        // Seed balance 10
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $u->id,
            'amount' => '10.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
        ]);

        // Positive correction +5
        $this->post(route('panel_admin.agencies.advance.correction.store', $u, false), [
            'amount' => 5,
            'direction' => 'increase',
            'reason' => 'Pozitivna korekcija',
        ])->assertRedirect(route('panel_admin.agencies.show', $u, false));

        $this->assertTrue(AgencyAdvanceTransaction::query()
            ->where('agency_user_id', $u->id)
            ->where('type', AgencyAdvanceTransaction::TYPE_CORRECTION)
            ->where('amount', '5.00')
            ->where('note', 'Pozitivna korekcija')
            ->exists());

        // Negative correction -3 (allowed)
        $this->post(route('panel_admin.agencies.advance.correction.store', $u, false), [
            'amount' => 3,
            'direction' => 'decrease',
            'reason' => 'Negativna korekcija',
        ])->assertRedirect(route('panel_admin.agencies.show', $u, false));

        $this->assertTrue(AgencyAdvanceTransaction::query()
            ->where('agency_user_id', $u->id)
            ->where('type', AgencyAdvanceTransaction::TYPE_CORRECTION)
            ->where('amount', '-3.00')
            ->where('note', 'Negativna korekcija')
            ->exists());

        $countBefore = AgencyAdvanceTransaction::query()->where('agency_user_id', $u->id)->count();

        // Attempt to reduce below zero (balance currently 10 + 5 - 3 = 12)
        $this->post(route('panel_admin.agencies.advance.correction.store', $u, false), [
            'amount' => 20,
            'direction' => 'decrease',
            'reason' => 'Preveliko umanjenje',
        ])->assertSessionHasErrors(['amount']);

        $this->assertSame($countBefore, AgencyAdvanceTransaction::query()->where('agency_user_id', $u->id)->count());
    }

    public function test_correction_validation_requires_reason_and_amount_gt_zero(): void
    {
        config(['features.advance_payments' => true]);

        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $u = User::factory()->create(['name' => 'Val']);

        $this->post(route('panel_admin.agencies.advance.correction.store', $u, false), [
            'amount' => 0,
            'direction' => 'increase',
            'reason' => 'x',
        ])->assertSessionHasErrors(['amount', 'reason']);
    }

    public function test_resend_confirmation_sends_for_paid_topup_without_sent_at_and_does_not_send_twice(): void
    {
        config(['features.advance_payments' => true]);

        Mail::fake();

        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $agency = User::factory()->create(['email' => 'agency@example.com']);

        $topup = AgencyAdvanceTopup::query()->create([
            'agency_user_id' => $agency->id,
            'merchant_transaction_id' => 'mtid-adv-1',
            'amount' => '100.00',
            'status' => AgencyAdvanceTopup::STATUS_PAID,
            'paid_at' => now(),
            'confirmation_sent_at' => null,
            'confirmation_email' => null,
            'confirmation_sending_at' => null,
        ]);
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => 'advance_topup',
            'reference_id' => $topup->id,
            'merchant_transaction_id' => $topup->merchant_transaction_id,
        ]);

        $this->post(route('panel_admin.agencies.advance.topups.confirmation.resend', ['user' => $agency->id, 'topup' => $topup->id], false))
            ->assertRedirect(route('panel_admin.agencies.show', $agency, false))
            ->assertSessionHas('status');

        $topup->refresh();
        $this->assertNotNull($topup->confirmation_sent_at);
        $this->assertSame('agency@example.com', (string) $topup->confirmation_email);
        Mail::assertSent(AdvanceTopupConfirmationMail::class, 1);

        // Second resend should not send again
        $this->post(route('panel_admin.agencies.advance.topups.confirmation.resend', ['user' => $agency->id, 'topup' => $topup->id], false))
            ->assertRedirect(route('panel_admin.agencies.show', $agency, false))
            ->assertSessionHas('message');
        Mail::assertSent(AdvanceTopupConfirmationMail::class, 1);
    }

    public function test_resend_confirmation_rejects_non_paid_or_wrong_agency_or_feature_flag_off(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $a1 = User::factory()->create();
        $a2 = User::factory()->create();

        config(['features.advance_payments' => true]);
        $pending = AgencyAdvanceTopup::query()->create([
            'agency_user_id' => $a1->id,
            'merchant_transaction_id' => 'mtid-adv-p',
            'amount' => '50.00',
            'status' => AgencyAdvanceTopup::STATUS_PENDING,
        ]);
        $this->post(route('panel_admin.agencies.advance.topups.confirmation.resend', ['user' => $a1->id, 'topup' => $pending->id], false))
            ->assertRedirect(route('panel_admin.agencies.show', $a1, false));

        // Wrong agency binding
        $paid = AgencyAdvanceTopup::query()->create([
            'agency_user_id' => $a1->id,
            'merchant_transaction_id' => 'mtid-adv-x',
            'amount' => '10.00',
            'status' => AgencyAdvanceTopup::STATUS_PAID,
            'paid_at' => now(),
        ]);
        $this->post(route('panel_admin.agencies.advance.topups.confirmation.resend', ['user' => $a2->id, 'topup' => $paid->id], false))
            ->assertNotFound();

        // Feature flag off blocks route
        config(['features.advance_payments' => false]);
        $this->post(route('panel_admin.agencies.advance.topups.confirmation.resend', ['user' => $a1->id, 'topup' => $paid->id], false))
            ->assertNotFound();
    }
}

