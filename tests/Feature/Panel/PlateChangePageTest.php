<?php

namespace Tests\Feature\Panel;

use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Support\ReservationKind;
use Carbon\Carbon;
use Database\Seeders\UiTranslationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlateChangePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UiTranslationsSeeder::class);
    }

    public function test_menu_shows_promjena_tablica_in_cg_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/locale/cg')
            ->assertRedirect();

        $this->get(route('panel.reservations', [], false))
            ->assertOk()
            ->assertSee('Promjena tablica', false);
    }

    public function test_page_title_shows_promjena_tablica_in_cg_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/locale/cg')
            ->assertRedirect();

        $this->get(route('panel.upcoming', [], false))
            ->assertOk()
            ->assertSee('Promjena tablica', false)
            ->assertSee('Na ovoj stranici možete promijeniti registarsku tablicu', false);
    }

    public function test_eligible_time_slots_reservation_still_allows_plate_change(): void
    {
        [$user, $reservation] = $this->upcomingTimeSlotsReservationWithSpareVehicle();

        $this->actingAs($user)
            ->get('/locale/cg')
            ->assertRedirect();

        $this->get(route('panel.upcoming', [], false))
            ->assertOk()
            ->assertSee('Promijeni tablicu', false)
            ->assertDontSee('Promjena tablice nije dostupna za dnevnu naknadu.', false);
    }

    public function test_daily_fee_reservation_does_not_show_plate_change_action(): void
    {
        $user = User::factory()->create();
        $vt = $this->busType();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'license_plate' => 'DN001',
            'vehicle_type_id' => $vt->id,
        ]);

        Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'reservation_kind' => ReservationKind::DAILY_TICKET,
            'drop_off_time_slot_id' => null,
            'pick_up_time_slot_id' => null,
            'reservation_date' => Carbon::now()->addDays(2)->toDateString(),
            'user_name' => 'Agency',
            'country' => 'ME',
            'license_plate' => $vehicle->license_plate,
            'vehicle_type_id' => $vt->id,
            'email' => $user->email,
            'status' => 'paid',
            'invoice_amount' => 10,
        ]);

        $this->actingAs($user)
            ->get('/locale/cg')
            ->assertRedirect();

        $this->get(route('panel.upcoming', [], false))
            ->assertOk()
            ->assertSee('Promjena tablice nije dostupna za dnevnu naknadu.', false)
            ->assertDontSee('Promijeni tablicu', false);
    }

    public function test_direct_plate_change_for_daily_ticket_is_rejected(): void
    {
        $user = User::factory()->create();
        $vt = $this->busType();
        $vehicleA = Vehicle::query()->create([
            'user_id' => $user->id,
            'license_plate' => 'DN100',
            'vehicle_type_id' => $vt->id,
        ]);
        $vehicleB = Vehicle::query()->create([
            'user_id' => $user->id,
            'license_plate' => 'DN200',
            'vehicle_type_id' => $vt->id,
        ]);

        $reservation = Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicleA->id,
            'reservation_kind' => ReservationKind::DAILY_TICKET,
            'drop_off_time_slot_id' => null,
            'pick_up_time_slot_id' => null,
            'reservation_date' => Carbon::now()->addDays(2)->toDateString(),
            'user_name' => 'Agency',
            'country' => 'ME',
            'license_plate' => $vehicleA->license_plate,
            'vehicle_type_id' => $vt->id,
            'email' => $user->email,
            'status' => 'paid',
            'invoice_amount' => 10,
        ]);

        $this->actingAs($user)
            ->from(route('panel.upcoming', [], false))
            ->patch(route('panel.reservations.vehicle', $reservation->id, false), [
                'vehicle_id' => $vehicleB->id,
            ])
            ->assertSessionHasErrors('vehicle_id');

        $reservation->refresh();
        $this->assertSame('DN100', $reservation->license_plate);
        $this->assertSame((int) $vehicleA->id, (int) $reservation->vehicle_id);
    }

    public function test_time_slots_plate_change_workflow_still_updates_vehicle(): void
    {
        [$user, $reservation, $replacement] = $this->upcomingTimeSlotsReservationWithSpareVehicle();

        $this->actingAs($user)
            ->from(route('panel.upcoming', [], false))
            ->patch(route('panel.reservations.vehicle', $reservation->id, false), [
                'vehicle_id' => $replacement->id,
            ])
            ->assertRedirect(route('panel.upcoming', [], false));

        $reservation->refresh();
        $this->assertSame($replacement->license_plate, $reservation->license_plate);
        $this->assertSame((int) $replacement->id, (int) $reservation->vehicle_id);
    }

    /**
     * @return array{0: User, 1: Reservation}
     */
    private function upcomingTimeSlotsReservationWithSpareVehicle(): array
    {
        $user = User::factory()->create();
        $vt = $this->busType();
        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $date = Carbon::now()->addDays(3)->toDateString();

        $current = Vehicle::query()->create([
            'user_id' => $user->id,
            'license_plate' => 'TS100',
            'vehicle_type_id' => $vt->id,
        ]);
        $replacement = Vehicle::query()->create([
            'user_id' => $user->id,
            'license_plate' => 'TS200',
            'vehicle_type_id' => $vt->id,
        ]);

        $reservation = Reservation::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $current->id,
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotB->id,
            'reservation_date' => $date,
            'user_name' => 'Agency',
            'country' => 'ME',
            'license_plate' => $current->license_plate,
            'vehicle_type_id' => $vt->id,
            'email' => $user->email,
            'status' => 'paid',
            'invoice_amount' => 10,
        ]);

        return [$user, $reservation, $replacement];
    }

    private function busType(): VehicleType
    {
        $vt = VehicleType::query()->create(['price' => 10]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'en',
            'name' => 'Bus',
            'description' => null,
        ]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'Autobus',
            'description' => null,
        ]);

        return $vt;
    }
}
