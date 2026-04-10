<?php

namespace Tests\Feature\AdminPanel;

use App\Jobs\SendFreeReservationConfirmationJob;
use App\Models\Admin;
use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminPanelFreeReservationTest extends TestCase
{
    use RefreshDatabase;

    private function seedAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'freeadmin',
            'email' => 'free-admin@example.com',
            'password' => bcrypt('secret-password-fr'),
            'control_access' => false,
            'admin_access' => true,
        ]);
    }

    /**
     * @return array{0: string, 1: ListOfTimeSlot, 2: ListOfTimeSlot, 3: VehicleType}
     */
    private function seedSlotsAndVehicle(string $date): array
    {
        $s1 = ListOfTimeSlot::query()->create(['time_slot' => '08:00 - 08:20']);
        $s2 = ListOfTimeSlot::query()->create(['time_slot' => '09:00 - 09:20']);
        $vt = VehicleType::query()->create(['price' => 10]);
        foreach (['en', 'cg'] as $locale) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $locale,
                'name' => 'Autobus',
                'description' => null,
            ]);
        }
        foreach ([$s1, $s2] as $slot) {
            DailyParkingData::query()->create([
                'date' => $date,
                'time_slot_id' => $slot->id,
                'capacity' => 5,
                'reserved' => 0,
                'pending' => 0,
                'is_blocked' => false,
            ]);
        }

        return [$date, $s1, $s2, $vt];
    }

    public function test_admin_can_create_free_reservation_without_temp_data(): void
    {
        Queue::fake();

        $admin = $this->seedAdmin();
        $date = Carbon::now()->addDay()->toDateString();
        [$date, $s1, $s2, $vt] = $this->seedSlotsAndVehicle($date);

        $this->actingAs($admin, 'panel_admin');

        $response = $this->post(route('panel_admin.free-reservations.store', [], false), [
            'reservation_date' => $date,
            'drop_off_time_slot_id' => $s1->id,
            'pick_up_time_slot_id' => $s2->id,
            'vehicle_type_id' => $vt->id,
            'name' => 'Škola Primjer',
            'country' => 'ME',
            'license_plate' => 'KO123AB',
            'email' => 'skola@example.com',
        ]);

        $response->assertRedirect(route('panel_admin.free-reservations', [], false));
        $response->assertSessionHas('status');

        $r = Reservation::query()->where('email', 'skola@example.com')->first();
        $this->assertNotNull($r);
        $this->assertSame('free', $r->status);
        $this->assertTrue($r->created_by_admin);
        $this->assertNull($r->user_id);
        $this->assertSame('cg', $r->preferred_locale);
        $this->assertSame('0.00', (string) $r->invoice_amount);

        $this->assertSame(1, (int) DailyParkingData::query()->whereDate('date', $date)->where('time_slot_id', $s1->id)->value('reserved'));
        $this->assertSame(1, (int) DailyParkingData::query()->whereDate('date', $date)->where('time_slot_id', $s2->id)->value('reserved'));

        Queue::assertPushed(SendFreeReservationConfirmationJob::class, function (SendFreeReservationConfirmationJob $job) use ($r): bool {
            return $job->reservationId === $r->id;
        });
    }

    public function test_slot_conflict_preserves_guest_fields_and_clears_slots_in_redirect(): void
    {
        $admin = $this->seedAdmin();
        $date = Carbon::now()->addDay()->toDateString();
        [$date, $s1, $s2, $vt] = $this->seedSlotsAndVehicle($date);

        DailyParkingData::query()
            ->whereDate('date', $date)
            ->where('time_slot_id', $s1->id)
            ->update(['reserved' => 5]);

        $this->actingAs($admin, 'panel_admin');

        $response = $this->post(route('panel_admin.free-reservations.store', [], false), [
            'reservation_date' => $date,
            'drop_off_time_slot_id' => $s1->id,
            'pick_up_time_slot_id' => $s2->id,
            'vehicle_type_id' => $vt->id,
            'name' => 'Zadržano Ime',
            'country' => 'ME',
            'license_plate' => 'AB999CD',
            'email' => 'zadrzano@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $url = (string) $response->headers->get('Location');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('Zadržano Ime', $query['name'] ?? '');
        $this->assertSame((string) $vt->id, $query['vehicle_type_id'] ?? '');
        $this->assertArrayNotHasKey('reservation_date', $query);
        $this->assertArrayNotHasKey('drop_off_time_slot_id', $query);

        $this->assertSame(0, Reservation::query()->count());
    }

    public function test_free_reservations_page_renders_for_panel_admin(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin')
            ->get(route('panel_admin.free-reservations', [], false))
            ->assertOk()
            ->assertSee('Napravi besplatnu rezervaciju', false);
    }
}
