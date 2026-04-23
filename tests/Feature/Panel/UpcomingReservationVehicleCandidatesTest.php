<?php

namespace Tests\Feature\Panel;

use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpcomingReservationVehicleCandidatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_dropdown_excludes_conflicting_candidates_but_allows_cross_match(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $vt = VehicleType::query()->create(['price' => 10]);
        VehicleTypeTranslation::query()->create(['vehicle_type_id' => $vt->id, 'locale' => 'en', 'name' => 'Bus', 'description' => null]);
        VehicleTypeTranslation::query()->create(['vehicle_type_id' => $vt->id, 'locale' => 'cg', 'name' => 'Autobus', 'description' => null]);

        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $date = Carbon::now()->addDays(3)->toDateString();

        $current = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO111', 'vehicle_type_id' => $vt->id]);
        $conflict = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO222', 'vehicle_type_id' => $vt->id]);
        $crossOk = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO333', 'vehicle_type_id' => $vt->id]);

        // Conflict vehicle has same-date reservation with same drop (drop=drop) -> should be excluded.
        Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $conflict->id,
            'merchant_transaction_id' => 'mt10',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotA->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $conflict->license_plate,
            'vehicle_type_id' => $vt->id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        // Cross-match vehicle has swapped slots -> should be allowed.
        Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $crossOk->id,
            'merchant_transaction_id' => 'mt11',
            'drop_off_time_slot_id' => $slotB->id,
            'pick_up_time_slot_id' => $slotA->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $crossOk->license_plate,
            'vehicle_type_id' => $vt->id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        // Upcoming reservation to edit.
        Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $current->id,
            'merchant_transaction_id' => 'mt12',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotB->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $current->license_plate,
            'vehicle_type_id' => $vt->id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        $html = $this->get(route('panel.upcoming', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($current->license_plate, $html);
        // Conflict vehicle exists on the page (it has its own reservation row), but must not be offered as an option.
        $this->assertStringNotContainsString('value="'.$conflict->id.'"', $html);
        $this->assertStringContainsString($crossOk->license_plate, $html);
    }
}

