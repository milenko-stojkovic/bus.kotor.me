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

class ReservationVehicleChangePaidCategoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{low: VehicleType, mid: VehicleType, high: VehicleType} */
    private function seedTypes(): array
    {
        $low = VehicleType::query()->create(['price' => 10]);
        $mid = VehicleType::query()->create(['price' => 20]);
        $high = VehicleType::query()->create(['price' => 30]);

        foreach ([$low, $mid, $high] as $t) {
            VehicleTypeTranslation::query()->create(['vehicle_type_id' => $t->id, 'locale' => 'en', 'name' => 'T'.$t->id, 'description' => null]);
            VehicleTypeTranslation::query()->create(['vehicle_type_id' => $t->id, 'locale' => 'cg', 'name' => 'T'.$t->id, 'description' => null]);
        }

        return ['low' => $low, 'mid' => $mid, 'high' => $high];
    }

    /** @return array{user: User, reservation: Reservation, slotA: ListOfTimeSlot, slotB: ListOfTimeSlot, date: string} */
    private function seedPaidHighReservation(): array
    {
        $types = $this->seedTypes();
        $user = User::factory()->create();
        $this->actingAs($user);

        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $date = Carbon::now()->addDays(3)->toDateString();

        $highVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'license_plate' => 'KO-HIGH',
            'vehicle_type_id' => $types['high']->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $reservation = Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $highVehicle->id,
            'merchant_transaction_id' => 'mt-paid-high',
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotB->id,
            'reservation_date' => $date,
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $highVehicle->license_plate,
            'vehicle_type_id' => $types['high']->id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 30.00,
        ]);

        return [
            'user' => $user,
            'types' => $types,
            'reservation' => $reservation,
            'highVehicle' => $highVehicle,
            'slotA' => $slotA,
            'slotB' => $slotB,
            'date' => $date,
        ];
    }

    public function test_a_paid_reservation_can_change_to_lower_category_vehicle(): void
    {
        $ctx = $this->seedPaidHighReservation();
        $lowVehicle = Vehicle::query()->create([
            'user_id' => $ctx['user']->id,
            'license_plate' => 'KO-LOW',
            'vehicle_type_id' => $ctx['types']['low']->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->patch(route('panel.reservations.vehicle', $ctx['reservation']->id, false), [
            'vehicle_id' => $lowVehicle->id,
        ])->assertRedirect(route('panel.upcoming', [], false));

        $ctx['reservation']->refresh();
        $this->assertSame($lowVehicle->id, (int) $ctx['reservation']->vehicle_id);
        $this->assertSame('KO-LOW', $ctx['reservation']->license_plate);
    }

    public function test_b_after_lower_change_user_can_switch_back_to_originally_paid_category(): void
    {
        $ctx = $this->seedPaidHighReservation();
        $lowVehicle = Vehicle::query()->create([
            'user_id' => $ctx['user']->id,
            'license_plate' => 'KO-LOW',
            'vehicle_type_id' => $ctx['types']['low']->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);
        $midVehicle = Vehicle::query()->create([
            'user_id' => $ctx['user']->id,
            'license_plate' => 'KO-MID',
            'vehicle_type_id' => $ctx['types']['mid']->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->patch(route('panel.reservations.vehicle', $ctx['reservation']->id, false), [
            'vehicle_id' => $lowVehicle->id,
        ])->assertRedirect();

        $html = $this->get(route('panel.upcoming', [], false))->assertOk()->getContent();

        $this->assertStringContainsString('value="'.$midVehicle->id.'"', $html);
        $this->assertStringContainsString('value="'.$ctx['highVehicle']->id.'"', $html);

        $this->patch(route('panel.reservations.vehicle', $ctx['reservation']->id, false), [
            'vehicle_id' => $ctx['highVehicle']->id,
        ])->assertRedirect();

        $ctx['reservation']->refresh();
        $this->assertSame($ctx['highVehicle']->id, (int) $ctx['reservation']->vehicle_id);
    }

    public function test_c_vehicle_higher_than_paid_category_is_not_offered_on_upcoming_page(): void
    {
        $ctx = $this->seedPaidHighReservation();
        $lowVehicle = Vehicle::query()->create([
            'user_id' => $ctx['user']->id,
            'license_plate' => 'KO-LOW',
            'vehicle_type_id' => $ctx['types']['low']->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->patch(route('panel.reservations.vehicle', $ctx['reservation']->id, false), [
            'vehicle_id' => $lowVehicle->id,
        ])->assertRedirect();

        $tooHigh = Vehicle::query()->create([
            'user_id' => $ctx['user']->id,
            'license_plate' => 'KO-TOOHIGH',
            'vehicle_type_id' => VehicleType::query()->create(['price' => 40])->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $html = $this->get(route('panel.upcoming', [], false))->assertOk()->getContent();

        $this->assertStringNotContainsString('value="'.$tooHigh->id.'"', $html);
    }

    public function test_d_server_rejects_forged_patch_to_higher_than_paid_category(): void
    {
        $ctx = $this->seedPaidHighReservation();
        $tooHigh = Vehicle::query()->create([
            'user_id' => $ctx['user']->id,
            'license_plate' => 'KO-TOOHIGH',
            'vehicle_type_id' => VehicleType::query()->create(['price' => 40])->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->patch(route('panel.reservations.vehicle', $ctx['reservation']->id, false), [
            'vehicle_id' => $tooHigh->id,
        ])->assertSessionHasErrors(['vehicle_id']);

        $ctx['reservation']->refresh();
        $this->assertSame($ctx['highVehicle']->id, (int) $ctx['reservation']->vehicle_id);
    }

    public function test_e_vehicle_type_id_snapshot_unchanged_after_vehicle_change(): void
    {
        $ctx = $this->seedPaidHighReservation();
        $paidTypeId = (int) $ctx['reservation']->vehicle_type_id;

        $lowVehicle = Vehicle::query()->create([
            'user_id' => $ctx['user']->id,
            'license_plate' => 'KO-LOW',
            'vehicle_type_id' => $ctx['types']['low']->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->patch(route('panel.reservations.vehicle', $ctx['reservation']->id, false), [
            'vehicle_id' => $lowVehicle->id,
        ])->assertRedirect();

        $ctx['reservation']->refresh();
        $this->assertSame($paidTypeId, (int) $ctx['reservation']->vehicle_type_id);
    }

    public function test_f_invoice_amount_unchanged_after_vehicle_change(): void
    {
        $ctx = $this->seedPaidHighReservation();
        $lowVehicle = Vehicle::query()->create([
            'user_id' => $ctx['user']->id,
            'license_plate' => 'KO-LOW',
            'vehicle_type_id' => $ctx['types']['low']->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->patch(route('panel.reservations.vehicle', $ctx['reservation']->id, false), [
            'vehicle_id' => $lowVehicle->id,
        ])->assertRedirect();

        $ctx['reservation']->refresh();
        $this->assertSame('30.00', (string) $ctx['reservation']->invoice_amount);
    }

    public function test_g_same_slot_conflict_still_blocks_candidate(): void
    {
        $ctx = $this->seedPaidHighReservation();
        $conflict = Vehicle::query()->create([
            'user_id' => $ctx['user']->id,
            'license_plate' => 'KO-CONFLICT',
            'vehicle_type_id' => $ctx['types']['low']->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        Reservation::query()->create([
            'user_id' => $ctx['user']->id,
            'vehicle_id' => $conflict->id,
            'merchant_transaction_id' => 'mt-conflict',
            'drop_off_time_slot_id' => $ctx['slotA']->id,
            'pick_up_time_slot_id' => $ctx['slotA']->id,
            'reservation_date' => $ctx['date'],
            'user_name' => 'u',
            'country' => 'ME',
            'license_plate' => $conflict->license_plate,
            'vehicle_type_id' => $ctx['types']['low']->id,
            'email' => 'u@example.com',
            'preferred_locale' => 'en',
            'status' => 'paid',
            'invoice_amount' => 10,
        ]);

        $this->patch(route('panel.reservations.vehicle', $ctx['reservation']->id, false), [
            'vehicle_id' => $conflict->id,
        ])->assertSessionHasErrors(['vehicle_id']);
    }

    public function test_h_vehicle_removal_workflow_uses_paid_reservation_category_not_current_vehicle(): void
    {
        $ctx = $this->seedPaidHighReservation();
        $lowVehicle = Vehicle::query()->create([
            'user_id' => $ctx['user']->id,
            'license_plate' => 'KO-LOW',
            'vehicle_type_id' => $ctx['types']['low']->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);
        $midVehicle = Vehicle::query()->create([
            'user_id' => $ctx['user']->id,
            'license_plate' => 'KO-MID',
            'vehicle_type_id' => $ctx['types']['mid']->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->patch(route('panel.reservations.vehicle', $ctx['reservation']->id, false), [
            'vehicle_id' => $lowVehicle->id,
        ])->assertRedirect();

        $html = $this->get(route('panel.vehicles.remove', $lowVehicle->id, false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="'.$midVehicle->id.'"', $html);
        $this->assertStringContainsString('value="'.$ctx['highVehicle']->id.'"', $html);
    }
}
