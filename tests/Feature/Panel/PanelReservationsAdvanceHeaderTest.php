<?php

namespace Tests\Feature\Panel;

use App\Models\AgencyAdvanceTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PanelReservationsAdvanceHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_off_does_not_show_total_advance_header(): void
    {
        config(['features.advance_payments' => false]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $this->get(route('panel.reservations', [], false))
            ->assertOk()
            ->assertDontSee('Ukupan iznos avansa:', false);
    }

    public function test_feature_flag_on_shows_total_advance_header_from_ledger(): void
    {
        config(['features.advance_payments' => true]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
        ]);
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '-30.00',
            'type' => AgencyAdvanceTransaction::TYPE_USAGE,
        ]);
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '-5.00',
            'type' => AgencyAdvanceTransaction::TYPE_CORRECTION,
        ]);

        $this->get(route('panel.reservations', [], false))
            ->assertOk()
            ->assertSee('Ukupan iznos avansa:', false)
            ->assertSee('65.00 EUR', false);
    }
}

