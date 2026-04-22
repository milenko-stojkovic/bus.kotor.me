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

    public function test_analytics_date_from_min_is_oldest_realized_reservation_date_with_fallbacks(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $slot = ListOfTimeSlot::query()->create(['time_slot' => '09:00 - 09:20']);
        $vt = VehicleType::query()->create(['price' => 10]);

        // Past date => realized by definition (day < today).
        $past = Carbon::now()->subDays(10)->toDateString();
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-analytics-min-1',
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => $past,
            'user_name' => 'X',
            'country' => 'ME',
            'license_plate' => 'KO1',
            'vehicle_type_id' => $vt->id,
            'email' => 'x@example.com',
            'status' => 'paid',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->get(route('panel_admin.analytics', [], false))
            ->assertOk()
            ->assertSee('name="date_from"', false)
            ->assertSee('min="'.$past.'"', false);
    }

    public function test_ops_indicator_counts_paid_reservations_fully_in_free_zones(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $slotMorning = ListOfTimeSlot::query()->create(['time_slot' => '06:00 - 06:20']);
        $slotEvening = ListOfTimeSlot::query()->create(['time_slot' => '21:00 - 21:20']);
        $slotDayA = ListOfTimeSlot::query()->create(['time_slot' => '08:00 - 08:20']);
        $slotDayB = ListOfTimeSlot::query()->create(['time_slot' => '09:00 - 09:20']);

        $vt = VehicleType::query()->create(['price' => 10]);
        foreach (['cg', 'en'] as $loc) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $loc,
                'name' => 'VT',
                'description' => 'd',
            ]);
        }

        $d = Carbon::now()->addDays(2)->toDateString();
        foreach ([$slotMorning, $slotEvening, $slotDayA, $slotDayB] as $s) {
            DailyParkingData::query()->create([
                'date' => $d,
                'time_slot_id' => $s->id,
                'capacity' => 5,
                'reserved' => 0,
                'pending' => 0,
                'is_blocked' => false,
            ]);
        }

        // Should count: paid, both slots in free zones (morning + evening).
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-analytics-freezone-paid-1',
            'drop_off_time_slot_id' => $slotMorning->id,
            'pick_up_time_slot_id' => $slotEvening->id,
            'reservation_date' => $d,
            'user_name' => 'A',
            'country' => 'ME',
            'license_plate' => 'KO1',
            'vehicle_type_id' => $vt->id,
            'email' => 'a@example.com',
            'status' => 'paid',
            'invoice_amount' => '10.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        // Should NOT count: paid, mixed free morning + paid day window.
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-analytics-freezone-paid-mixed',
            'drop_off_time_slot_id' => $slotMorning->id,
            'pick_up_time_slot_id' => $slotDayA->id,
            'reservation_date' => $d,
            'user_name' => 'B',
            'country' => 'ME',
            'license_plate' => 'KO2',
            'vehicle_type_id' => $vt->id,
            'email' => 'b@example.com',
            'status' => 'paid',
            'invoice_amount' => '20.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        // Should NOT count: free, fully in free zones.
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-analytics-freezone-free-1',
            'drop_off_time_slot_id' => $slotMorning->id,
            'pick_up_time_slot_id' => $slotEvening->id,
            'reservation_date' => $d,
            'user_name' => 'C',
            'country' => 'ME',
            'license_plate' => 'KO3',
            'vehicle_type_id' => $vt->id,
            'email' => 'c@example.com',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $html = $this->get(route('panel_admin.analytics', [
            'show' => 1,
            'date_from' => $d,
            'date_to' => $d,
            'include_free' => 1,
        ], false))->assertOk()->getContent();

        $this->assertStringContainsString('Paid rezervacije u free terminima', $html);
        $this->assertMatchesRegularExpression(
            '/Paid rezervacije u free terminima[\s\S]*?<td[^>]*>\s*1\s*<\/td>/i',
            $html,
        );
    }

    public function test_ops_indicator_counts_double_paid_same_slot_pairs(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $slot1 = ListOfTimeSlot::query()->create(['time_slot' => '08:00 - 08:20']);
        $slot2 = ListOfTimeSlot::query()->create(['time_slot' => '09:00 - 09:20']);
        $slot3 = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);

        $vt = VehicleType::query()->create(['price' => 10]);
        foreach (['cg', 'en'] as $loc) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $loc,
                'name' => 'VT',
                'description' => 'd',
            ]);
        }

        $d = Carbon::now()->addDays(3)->toDateString();
        foreach ([$slot1, $slot2, $slot3] as $s) {
            DailyParkingData::query()->create([
                'date' => $d,
                'time_slot_id' => $s->id,
                'capacity' => 5,
                'reserved' => 0,
                'pending' => 0,
                'is_blocked' => false,
            ]);
        }

        // Pair that SHOULD count (same date, same plate, same drop-off).
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-dbl-1',
            'drop_off_time_slot_id' => $slot1->id,
            'pick_up_time_slot_id' => $slot2->id,
            'reservation_date' => $d,
            'user_name' => 'A',
            'country' => 'ME',
            'license_plate' => 'KO123',
            'vehicle_type_id' => $vt->id,
            'email' => 'a@example.com',
            'status' => 'paid',
            'invoice_amount' => '10.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-dbl-2',
            'drop_off_time_slot_id' => $slot1->id,
            'pick_up_time_slot_id' => $slot3->id,
            'reservation_date' => $d,
            'user_name' => 'B',
            'country' => 'ME',
            'license_plate' => 'KO123',
            'vehicle_type_id' => $vt->id,
            'email' => 'b@example.com',
            'status' => 'paid',
            'invoice_amount' => '10.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        // Same date/plate but only cross-match (pick-up of first == drop-off of second) -> should NOT count.
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-dbl-x1',
            'drop_off_time_slot_id' => $slot2->id,
            'pick_up_time_slot_id' => $slot3->id,
            'reservation_date' => $d,
            'user_name' => 'X1',
            'country' => 'ME',
            'license_plate' => 'KO555',
            'vehicle_type_id' => $vt->id,
            'email' => 'x1@example.com',
            'status' => 'paid',
            'invoice_amount' => '10.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-dbl-x2',
            'drop_off_time_slot_id' => $slot3->id,
            'pick_up_time_slot_id' => $slot1->id,
            'reservation_date' => $d,
            'user_name' => 'X2',
            'country' => 'ME',
            'license_plate' => 'KO555',
            'vehicle_type_id' => $vt->id,
            'email' => 'x2@example.com',
            'status' => 'paid',
            'invoice_amount' => '10.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        // Same date/plate but NO overlap -> should NOT add a pair.
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-dbl-3',
            'drop_off_time_slot_id' => $slot2->id,
            'pick_up_time_slot_id' => $slot2->id,
            'reservation_date' => $d,
            'user_name' => 'C',
            'country' => 'ME',
            'license_plate' => 'KO999',
            'vehicle_type_id' => $vt->id,
            'email' => 'c@example.com',
            'status' => 'paid',
            'invoice_amount' => '10.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-dbl-4',
            'drop_off_time_slot_id' => $slot3->id,
            'pick_up_time_slot_id' => $slot3->id,
            'reservation_date' => $d,
            'user_name' => 'D',
            'country' => 'ME',
            'license_plate' => 'KO999',
            'vehicle_type_id' => $vt->id,
            'email' => 'd@example.com',
            'status' => 'paid',
            'invoice_amount' => '10.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        // paid + free should NOT count
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-dbl-5',
            'drop_off_time_slot_id' => $slot1->id,
            'pick_up_time_slot_id' => $slot1->id,
            'reservation_date' => $d,
            'user_name' => 'E',
            'country' => 'ME',
            'license_plate' => 'KO777',
            'vehicle_type_id' => $vt->id,
            'email' => 'e@example.com',
            'status' => 'paid',
            'invoice_amount' => '10.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-dbl-6',
            'drop_off_time_slot_id' => $slot1->id,
            'pick_up_time_slot_id' => $slot1->id,
            'reservation_date' => $d,
            'user_name' => 'F',
            'country' => 'ME',
            'license_plate' => 'KO777',
            'vehicle_type_id' => $vt->id,
            'email' => 'f@example.com',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        // Same plate, different date -> should NOT count
        $d2 = Carbon::now()->addDays(4)->toDateString();
        foreach ([$slot1, $slot2, $slot3] as $s) {
            DailyParkingData::query()->create([
                'date' => $d2,
                'time_slot_id' => $s->id,
                'capacity' => 5,
                'reserved' => 0,
                'pending' => 0,
                'is_blocked' => false,
            ]);
        }
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-dbl-7',
            'drop_off_time_slot_id' => $slot1->id,
            'pick_up_time_slot_id' => $slot1->id,
            'reservation_date' => $d2,
            'user_name' => 'G',
            'country' => 'ME',
            'license_plate' => 'KO123',
            'vehicle_type_id' => $vt->id,
            'email' => 'g@example.com',
            'status' => 'paid',
            'invoice_amount' => '10.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $html = $this->get(route('panel_admin.analytics', [
            'show' => 1,
            'date_from' => $d,
            'date_to' => $d,
            // include_free should not affect this indicator
            'include_free' => 0,
        ], false))->assertOk()->getContent();

        $this->assertStringContainsString('Duplo plaćanje istog termina', $html);
        $this->assertMatchesRegularExpression(
            '/Duplo plaćanje istog termina[\s\S]*?<td[^>]*>\s*1\s*<\/td>/i',
            $html,
        );
    }
}

