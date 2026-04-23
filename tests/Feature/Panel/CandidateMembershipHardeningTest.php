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

class CandidateMembershipHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_vehicle_change_rejects_vehicle_id_not_in_candidate_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $vt = VehicleType::query()->create(['price' => 10]);
        VehicleTypeTranslation::query()->create(['vehicle_type_id' => $vt->id, 'locale' => 'en', 'name' => 'Bus', 'description' => null]);

        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $date = Carbon::now()->addDays(3)->toDateString();

        $current = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO111', 'vehicle_type_id' => $vt->id]);
        $conflicting = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO222', 'vehicle_type_id' => $vt->id]);

        // Make conflicting vehicle unavailable by giving it an upcoming reservation with drop=drop on same date.
        Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $conflicting->id,
            'merchant_transaction_id' => 'mt_mem_1',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotA->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $conflicting->license_plate,
            'vehicle_type_id' => $vt->id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        $r = Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $current->id,
            'merchant_transaction_id' => 'mt_mem_2',
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

        $this->patch(route('panel.reservations.vehicle', $r->id, false), [
            'vehicle_id' => $conflicting->id, // injected: not a candidate
        ])->assertSessionHasErrors(['vehicle_id']);
    }

    public function test_vehicle_removal_workflow_rejects_replacement_not_in_candidate_list_and_does_not_change_anything(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $vt = VehicleType::query()->create(['price' => 10]);
        VehicleTypeTranslation::query()->create(['vehicle_type_id' => $vt->id, 'locale' => 'en', 'name' => 'Bus', 'description' => null]);

        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $date = Carbon::now()->addDays(3)->toDateString();

        $target = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO111', 'vehicle_type_id' => $vt->id]);
        $bad = Vehicle::query()->create(['user_id' => $user->id, 'license_plate' => 'KO222', 'vehicle_type_id' => $vt->id]);

        // bad is not a candidate (conflict).
        Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $bad->id,
            'merchant_transaction_id' => 'mt_mem_3',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotA->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $bad->license_plate,
            'vehicle_type_id' => $vt->id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        $r = Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $target->id,
            'merchant_transaction_id' => 'mt_mem_4',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotB->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $target->license_plate,
            'vehicle_type_id' => $vt->id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 1,
        ]);

        $this->post(route('panel.vehicles.remove.apply', $target->id, false), [
            'replacements' => [
                $r->id => $bad->id, // injected: not a candidate
            ],
        ])->assertRedirect(); // back with error

        $r->refresh();
        $this->assertSame($target->id, (int) $r->vehicle_id);
        $this->assertSame(1, Vehicle::query()->whereKey($target->id)->count());
    }
}

