<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\SystemConfig;
use App\Models\VehicleType;
use App\Support\ReservationKind;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DailyCapacityChartsRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_warnings_dashboard_renders_today_and_tomorrow_capacity_charts_and_dataset_is_correct(): void
    {
        // Ensure time slots 1..41 exist (x-axis = slot numbers).
        for ($i = 1; $i <= 41; $i++) {
            ListOfTimeSlot::query()->create(['time_slot' => sprintf('%02d:00 - %02d:20', ($i - 1) % 24, ($i - 1) % 24)]);
        }

        // Capacity from system config (NOT from daily_parking_data.capacity).
        SystemConfig::setValue('available_parking_slots', 3);

        $tz = (string) config('reservations.operations_timezone', 'Europe/Podgorica');
        $today = Carbon::now($tz)->startOfDay()->toDateString();
        $tomorrow = Carbon::now($tz)->startOfDay()->addDay()->toDateString();

        // Put values only for slot #1 today; total > capacity should still be represented.
        $slot1 = ListOfTimeSlot::query()->orderBy('id')->firstOrFail();
        DailyParkingData::query()->create([
            'date' => $today,
            'time_slot_id' => $slot1->id,
            'capacity' => 3,
            'reserved' => 2,
            'pending' => 4, // over capacity on purpose
            'is_blocked' => false,
        ]);

        $admin = Admin::query()->create([
            'username' => 'capadmin',
            'email' => 'cap-admin@example.com',
            'password' => bcrypt('secret-password-cap'),
            'control_access' => false,
            'admin_access' => true,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $res = $this->get(route('panel_admin.dashboard', [], false));
        $res->assertOk();

        // Charts present.
        $res->assertSee('capacity-chart-admin-today', false);
        $res->assertSee('capacity-chart-admin-tomorrow', false);

        // Dataset script present and contains today/tomorrow and 41 slots.
        $html = $res->getContent();
        $this->assertStringContainsString('capacity-chart-admin-today-data', $html);
        $this->assertStringContainsString('capacity-chart-admin-tomorrow-data', $html);

        // Basic correctness: capacity from config and over-capacity total preserved.
        $this->assertStringContainsString('"capacity":3', $html);
        $this->assertStringContainsString('"date":"'.$today.'"', $html);
        $this->assertStringContainsString('"date":"'.$tomorrow.'"', $html);
        $this->assertStringContainsString('"slot_number":41', $html);
        $this->assertStringContainsString('"reserved":2', $html);
        $this->assertStringContainsString('"pending":4', $html);
        $this->assertStringContainsString('"total":6', $html);
        $this->assertStringContainsString('Ukupno rezervacija', $html);
        $this->assertStringContainsString('"reservations_total":0', $html);
    }

    public function test_admin_warnings_dashboard_renders_daily_fee_reservation_summaries_for_today_and_tomorrow(): void
    {
        $vt = VehicleType::query()->create(['price' => 15]);

        $tz = (string) config('reservations.operations_timezone', 'Europe/Podgorica');
        $today = Carbon::now($tz)->startOfDay()->toDateString();
        $tomorrow = Carbon::now($tz)->startOfDay()->addDay()->toDateString();

        $base = [
            'drop_off_time_slot_id' => null,
            'pick_up_time_slot_id' => null,
            'user_name' => 'Test',
            'country' => 'ME',
            'vehicle_type_id' => $vt->id,
            'email' => 'daily-fee@example.com',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
            'reservation_kind' => ReservationKind::DAILY_TICKET,
            'status' => 'paid',
            'invoice_amount' => '15.00',
        ];

        Reservation::query()->create(array_merge($base, [
            'merchant_transaction_id' => 'mt-daily-today-1',
            'license_plate' => 'KO1',
            'reservation_date' => $today,
        ]));
        Reservation::query()->create(array_merge($base, [
            'merchant_transaction_id' => 'mt-daily-today-2',
            'license_plate' => 'KO2',
            'reservation_date' => $today,
        ]));
        Reservation::query()->create(array_merge($base, [
            'merchant_transaction_id' => 'mt-daily-tomorrow-1',
            'license_plate' => 'KO3',
            'reservation_date' => $tomorrow,
        ]));

        $admin = Admin::query()->create([
            'username' => 'dailyfeeadmin',
            'email' => 'daily-fee-admin@example.com',
            'password' => bcrypt('secret-password-df'),
            'control_access' => false,
            'admin_access' => true,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $res = $this->get(route('panel_admin.dashboard', [], false));
        $res->assertOk();
        $res->assertSee('Ukupan broj rezervacija po dnevnim naknadama — danas', false);
        $res->assertSee('Ukupan broj rezervacija po dnevnim naknadama — sjutra', false);
        $res->assertSee('>2<', false);
        $res->assertSee('>1<', false);
    }
}

