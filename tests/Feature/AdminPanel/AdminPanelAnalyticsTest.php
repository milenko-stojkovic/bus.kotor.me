<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\TempData;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function seedAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'analyticsadmin',
            'email' => 'analytics-admin@example.com',
            'password' => bcrypt('secret-password-an'),
            'control_access' => false,
            'admin_access' => true,
        ]);
    }

    public function test_analytics_page_renders_for_panel_admin(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.analytics', [], false))
            ->assertOk()
            ->assertSee('Analitika', false);
    }

    public function test_analytics_show_and_pdf_work_for_simple_dataset(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $slot1 = ListOfTimeSlot::query()->create(['time_slot' => '08:00 - 08:20']);
        $slot2 = ListOfTimeSlot::query()->create(['time_slot' => '09:00 - 09:20']);

        $vt = VehicleType::query()->create(['price' => 50]);
        foreach (['cg', 'en'] as $loc) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $loc,
                'name' => $loc === 'cg' ? 'Veliki autobus' : 'Big bus',
                'description' => $loc === 'cg' ? 'preko 23 sjedišta' : 'over 23 seats',
            ]);
        }

        $d = Carbon::now()->addDay()->toDateString();
        foreach ([$slot1, $slot2] as $s) {
            DailyParkingData::query()->create([
                'date' => $d,
                'time_slot_id' => $s->id,
                'capacity' => 5,
                'reserved' => 0,
                'pending' => 0,
                'is_blocked' => false,
            ]);
        }

        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-analytics-1',
            'drop_off_time_slot_id' => $slot1->id,
            'pick_up_time_slot_id' => $slot2->id,
            'reservation_date' => $d,
            'user_name' => 'X',
            'country' => 'ME',
            'license_plate' => 'KO1',
            'vehicle_type_id' => $vt->id,
            'email' => 'x@example.com',
            'status' => 'paid',
            'invoice_amount' => '50.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        TempData::query()->create([
            'merchant_transaction_id' => 'mt-tmp-1',
            'drop_off_time_slot_id' => $slot1->id,
            'pick_up_time_slot_id' => $slot2->id,
            'reservation_date' => $d,
            'user_name' => 'X',
            'country' => 'ME',
            'license_plate' => 'KO1',
            'vehicle_type_id' => $vt->id,
            'email' => 'x@example.com',
            // SQLite test schema has limited enum: pending|failed|late_success.
            'status' => 'failed',
        ]);

        $resp = $this->get(route('panel_admin.analytics', [
            'show' => 1,
            'date_from' => $d,
            'date_to' => $d,
            'include_free' => 0,
        ], false));

        $resp->assertOk()
            ->assertSee('Ukupan prihod', false)
            ->assertSee('50.00 EUR', false);

        $pdf = $this->get(route('panel_admin.analytics.pdf', [
            'date_from' => $d,
            'date_to' => $d,
            'include_free' => 0,
        ], false));

        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
    }
}

