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

class VehicleRemovalReplacementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function seedTypes(): array
    {
        $low = VehicleType::query()->create(['price' => 10]);
        $mid = VehicleType::query()->create(['price' => 20]);
        $high = VehicleType::query()->create(['price' => 30]);

        foreach ([$low, $mid, $high] as $t) {
            VehicleTypeTranslation::query()->create(['vehicle_type_id' => $t->id, 'locale' => 'en', 'name' => 'T'.$t->id, 'description' => null]);
            VehicleTypeTranslation::query()->create(['vehicle_type_id' => $t->id, 'locale' => 'cg', 'name' => 'T'.$t->id, 'description' => null]);
        }

        return [$low, $mid, $high];
    }

    public function test_a_target_vehicle_without_upcoming_reservations_is_deleted_immediately(): void
    {
        [$low] = $this->seedTypes();
        $user = User::factory()->create();
        $this->actingAs($user);

        $v = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO111', 'vehicle_type_id' => $low->id]);

        $this->delete(route('panel.vehicles.destroy', $v->id, false))
            ->assertRedirect(route('panel.vehicles', [], false));

        $this->assertSame(0, Vehicle::query()->count());
    }

    public function test_b_target_vehicle_has_upcoming_but_one_reservation_has_no_candidates_shows_block_notice(): void
    {
        [$low] = $this->seedTypes();
        $user = User::factory()->create();
        $this->actingAs($user);

        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $date = Carbon::now()->addDays(3)->toDateString();

        $target = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO111', 'vehicle_type_id' => $low->id]);
        // No other vehicles in fleet -> no candidates.
        Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $target->id,
            'merchant_transaction_id' => 'mt1',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotB->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $target->license_plate,
            'vehicle_type_id' => $target->vehicle_type_id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        $this->get(route('panel.vehicles.remove', $target->id, false))
            ->assertOk()
            ->assertSee('cannot be removed', false);
    }

    public function test_c_candidate_higher_category_is_not_offered(): void
    {
        [$low, , $high] = $this->seedTypes();
        $user = User::factory()->create();
        $this->actingAs($user);

        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $date = Carbon::now()->addDays(3)->toDateString();

        $target = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO111', 'vehicle_type_id' => $low->id]);
        $tooHigh = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO999', 'vehicle_type_id' => $high->id]);

        $r = Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $target->id,
            'merchant_transaction_id' => 'mt2',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotB->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $target->license_plate,
            'vehicle_type_id' => $target->vehicle_type_id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        $html = $this->get(route('panel.vehicles.remove', $target->id, false))
            ->assertOk()
            ->getContent();

        // Higher category candidate must not be offered (by plate).
        $this->assertStringNotContainsString($tooHigh->license_plate, $html);
        $this->assertStringContainsString((string) $r->id, $html);
    }

    public function test_f_cross_match_is_not_a_conflict(): void
    {
        [$low] = $this->seedTypes();
        $user = User::factory()->create();
        $this->actingAs($user);

        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $date = Carbon::now()->addDays(3)->toDateString();

        $target = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO111', 'vehicle_type_id' => $low->id]);
        $candidate = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO222', 'vehicle_type_id' => $low->id]);

        // Candidate already has a reservation on same date with swapped slots (cross-match) -> should be allowed.
        Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $candidate->id,
            'merchant_transaction_id' => 'mt3',
            'drop_off_time_slot_id' => $slotB->id,
            'pick_up_time_slot_id' => $slotA->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $candidate->license_plate,
            'vehicle_type_id' => $candidate->vehicle_type_id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $target->id,
            'merchant_transaction_id' => 'mt4',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotB->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $target->license_plate,
            'vehicle_type_id' => $target->vehicle_type_id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        $html = $this->get(route('panel.vehicles.remove', $target->id, false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString((string) $candidate->id, $html);
    }

    public function test_g_drop_drop_is_a_conflict_and_candidate_is_not_offered(): void
    {
        [$low] = $this->seedTypes();
        $user = User::factory()->create();
        $this->actingAs($user);

        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $date = Carbon::now()->addDays(3)->toDateString();

        $target = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO111', 'vehicle_type_id' => $low->id]);
        $candidate = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO222', 'vehicle_type_id' => $low->id]);

        // Candidate has same-date reservation with same drop (drop=drop) -> conflict.
        Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $candidate->id,
            'merchant_transaction_id' => 'mt5',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotA->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $candidate->license_plate,
            'vehicle_type_id' => $candidate->vehicle_type_id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $target->id,
            'merchant_transaction_id' => 'mt6',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotB->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $target->license_plate,
            'vehicle_type_id' => $target->vehicle_type_id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        $html = $this->get(route('panel.vehicles.remove', $target->id, false))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($candidate->license_plate, $html);
    }

    public function test_h_successful_replacements_update_reservations_and_delete_target_vehicle(): void
    {
        [$low] = $this->seedTypes();
        $user = User::factory()->create();
        $this->actingAs($user);

        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $date = Carbon::now()->addDays(3)->toDateString();

        $target = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO111', 'vehicle_type_id' => $low->id]);
        $cand1 = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO222', 'vehicle_type_id' => $low->id]);

        $r1 = Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $target->id,
            'merchant_transaction_id' => 'mt7',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotB->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $target->license_plate,
            'vehicle_type_id' => $target->vehicle_type_id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        $this->post(route('panel.vehicles.remove.apply', $target->id, false), [
            'replacements' => [
                $r1->id => $cand1->id,
            ],
        ])->assertRedirect(route('panel.vehicles', [], false));

        $r1->refresh();
        $this->assertSame($cand1->id, (int) $r1->vehicle_id);
        $this->assertSame($cand1->license_plate, $r1->license_plate);
        $this->assertSame(0, Vehicle::query()->whereKey($target->id)->count());
    }

    public function test_i_invalid_combination_does_not_change_any_reservation_and_does_not_delete_vehicle(): void
    {
        [$low] = $this->seedTypes();
        $user = User::factory()->create();
        $this->actingAs($user);

        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $date = Carbon::now()->addDays(3)->toDateString();

        $target = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO111', 'vehicle_type_id' => $low->id]);
        $cand = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO222', 'vehicle_type_id' => $low->id]);

        // Two upcoming reservations (same date, same drop) -> assigning same candidate to both is invalid.
        $r1 = Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $target->id,
            'merchant_transaction_id' => 'mt8',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotB->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $target->license_plate,
            'vehicle_type_id' => $target->vehicle_type_id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);
        $r2 = Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $target->id,
            'merchant_transaction_id' => 'mt9',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotA->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $target->license_plate,
            'vehicle_type_id' => $target->vehicle_type_id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        $this->post(route('panel.vehicles.remove.apply', $target->id, false), [
            'replacements' => [
                $r1->id => $cand->id,
                $r2->id => $cand->id,
            ],
        ])->assertRedirect(); // back with error

        $r1->refresh();
        $r2->refresh();
        $this->assertSame($target->id, (int) $r1->vehicle_id);
        $this->assertSame($target->id, (int) $r2->vehicle_id);
        $this->assertSame(1, Vehicle::query()->whereKey($target->id)->count());
    }
}

