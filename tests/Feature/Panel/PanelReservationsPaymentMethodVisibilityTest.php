<?php

namespace Tests\Feature\Panel;

use App\Models\AgencyAdvanceTransaction;
use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\ReservationKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PanelReservationsPaymentMethodVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function seedAgencyWithVehicle(float $price = 10.0): array
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'country' => 'ME', 'lang' => 'cg']);
        $vt = VehicleType::query()->create(['price' => $price]);
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'license_plate' => 'KO555AA',
            'vehicle_type_id' => $vt->id,
        ]);

        return [$user, $vehicle];
    }

    private function seedSlotsAndCapacity(string $date, string $arrivalLabel, string $departureLabel): array
    {
        $drop = ListOfTimeSlot::query()->create(['time_slot' => $arrivalLabel]);
        $pick = ListOfTimeSlot::query()->create(['time_slot' => $departureLabel]);
        DailyParkingData::query()->create([
            'date' => $date,
            'time_slot_id' => $drop->id,
            'capacity' => 9,
            'reserved' => 0,
            'pending' => 0,
            'is_blocked' => false,
        ]);
        DailyParkingData::query()->create([
            'date' => $date,
            'time_slot_id' => $pick->id,
            'capacity' => 9,
            'reserved' => 0,
            'pending' => 0,
            'is_blocked' => false,
        ]);

        return [$drop, $pick];
    }

    public function test_agency_termini_paid_page_shows_card_payment_method_even_when_advance_feature_is_off(): void
    {
        config(['features.advance_payments' => false]);
        [$user, $vehicle] = $this->seedAgencyWithVehicle(price: 10.0);
        $date = now()->addDays(3)->toDateString();
        [$drop, $pick] = $this->seedSlotsAndCapacity($date, '10:00 - 10:20', '11:00 - 11:20');

        $html = $this->actingAs($user)->get(route('panel.reservations', [
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'reservation_date' => $date,
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'vehicle_id' => $vehicle->id,
        ], false))->assertOk()->getContent();

        $this->assertStringContainsString('name="payment_method"', $html);
        $this->assertStringContainsString('value="card"', $html);
        $this->assertStringNotContainsString('value="advance"', $html);
    }

    public function test_agency_termini_paid_page_shows_advance_payment_option_when_balance_is_sufficient(): void
    {
        config(['features.advance_payments' => true]);
        [$user, $vehicle] = $this->seedAgencyWithVehicle(price: 10.0);
        $date = now()->addDays(4)->toDateString();
        [$drop, $pick] = $this->seedSlotsAndCapacity($date, '10:00 - 10:20', '11:00 - 11:20');

        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '20.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
        ]);

        $html = $this->actingAs($user)->get(route('panel.reservations', [
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'reservation_date' => $date,
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'vehicle_id' => $vehicle->id,
        ], false))->assertOk()->getContent();

        $this->assertStringContainsString('name="payment_method"', $html);
        $this->assertStringContainsString('value="card"', $html);
        $this->assertStringContainsString('value="advance"', $html);
        $this->assertStringNotContainsString('value="advance" class="rounded border-red-200"  disabled', $html);
    }

    public function test_agency_termini_free_page_does_not_show_payment_method_selector(): void
    {
        config(['features.advance_payments' => true]);
        [$user, $vehicle] = $this->seedAgencyWithVehicle(price: 10.0);
        $date = now()->addDays(5)->toDateString();
        [$drop, $pick] = $this->seedSlotsAndCapacity($date, '00:00 - 07:00', '00:00 - 07:00');

        $html = $this->actingAs($user)->get(route('panel.reservations', [
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'reservation_date' => $date,
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'vehicle_id' => $vehicle->id,
        ], false))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="payment_method"', $html);
    }

    public function test_agency_daily_ticket_paid_page_shows_card_payment_method_even_when_advance_feature_is_off(): void
    {
        config(['features.advance_payments' => false]);
        [$user, $vehicle] = $this->seedAgencyWithVehicle(price: 12.5);
        $date = now()->addDays(6)->toDateString();

        $html = $this->actingAs($user)->get(route('panel.reservations', [
            'reservation_kind' => ReservationKind::DAILY_TICKET,
            'reservation_date' => $date,
            'vehicle_id' => $vehicle->id,
        ], false))->assertOk()->getContent();

        $this->assertStringContainsString('name="payment_method"', $html);
        $this->assertStringContainsString('value="card"', $html);
        $this->assertStringNotContainsString('value="advance"', $html);
    }

    public function test_agency_daily_ticket_paid_page_shows_advance_payment_option_when_balance_is_sufficient(): void
    {
        config(['features.advance_payments' => true]);
        [$user, $vehicle] = $this->seedAgencyWithVehicle(price: 12.5);
        $date = now()->addDays(7)->toDateString();

        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '20.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
        ]);

        $html = $this->actingAs($user)->get(route('panel.reservations', [
            'reservation_kind' => ReservationKind::DAILY_TICKET,
            'reservation_date' => $date,
            'vehicle_id' => $vehicle->id,
        ], false))->assertOk()->getContent();

        $this->assertStringContainsString('name="payment_method"', $html);
        $this->assertStringContainsString('value="card"', $html);
        $this->assertStringContainsString('value="advance"', $html);
    }
}

