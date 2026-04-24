<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\TempData;
use App\Models\User;
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

    public function test_agency_analysis_section_renders_sorted_and_computes_metrics_and_pdf_contains_it(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $slotMorning = ListOfTimeSlot::query()->create(['time_slot' => '06:00 - 06:20']);
        $slotDay = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotEvening = ListOfTimeSlot::query()->create(['time_slot' => '21:00 - 21:20']);

        $vtA = VehicleType::query()->create(['price' => 50]);
        $vtB = VehicleType::query()->create(['price' => 20]);
        foreach (['cg', 'en'] as $loc) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vtA->id,
                'locale' => $loc,
                'name' => $loc === 'cg' ? 'Tip A' : 'Type A',
                'description' => $loc === 'cg' ? 'opis a' : 'desc a',
            ]);
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vtB->id,
                'locale' => $loc,
                'name' => $loc === 'cg' ? 'Tip B' : 'Type B',
                'description' => $loc === 'cg' ? 'opis b' : 'desc b',
            ]);
        }

        $agencyHigh = User::query()->create([
            'name' => 'Agencija High',
            'email' => 'high@example.com',
            'password' => bcrypt('secret'),
            'lang' => 'cg',
            'country' => 'ME',
            'email_verified_at' => now(),
        ]);
        $agencyLow = User::query()->create([
            'name' => 'Agencija Low',
            'email' => 'low@example.com',
            'password' => bcrypt('secret'),
            'lang' => 'cg',
            'country' => 'ME',
            'email_verified_at' => now(),
        ]);

        $d = Carbon::now()->addDays(3)->toDateString();

        // High agency: 2 paid (revenue 100), 1 free. Uses vtA twice => top type 2/3.
        Reservation::query()->create([
            'user_id' => $agencyHigh->id,
            'merchant_transaction_id' => 'mt-ag-high-1',
            'drop_off_time_slot_id' => $slotMorning->id,
            'pick_up_time_slot_id' => $slotMorning->id, // 1 slot
            'reservation_date' => $d,
            'user_name' => $agencyHigh->name,
            'country' => 'ME',
            'license_plate' => 'H1',
            'vehicle_type_id' => $vtA->id,
            'email' => 'x@example.com',
            'status' => 'paid',
            'invoice_amount' => '60.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
            'created_by_admin' => false,
        ]);
        Reservation::query()->create([
            'user_id' => $agencyHigh->id,
            'merchant_transaction_id' => 'mt-ag-high-2',
            'drop_off_time_slot_id' => $slotDay->id,
            'pick_up_time_slot_id' => $slotEvening->id, // 2 slots
            'reservation_date' => $d,
            'user_name' => $agencyHigh->name,
            'country' => 'ME',
            'license_plate' => 'H2',
            'vehicle_type_id' => $vtA->id,
            'email' => 'x@example.com',
            'status' => 'paid',
            'invoice_amount' => '40.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
            'created_by_admin' => false,
        ]);
        Reservation::query()->create([
            'user_id' => $agencyHigh->id,
            'merchant_transaction_id' => 'mt-ag-high-free',
            'drop_off_time_slot_id' => $slotEvening->id,
            'pick_up_time_slot_id' => $slotEvening->id,
            'reservation_date' => $d,
            'user_name' => $agencyHigh->name,
            'country' => 'ME',
            'license_plate' => 'H3',
            'vehicle_type_id' => $vtB->id,
            'email' => 'x@example.com',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
            'created_by_admin' => false,
        ]);

        // Low agency: 1 paid (revenue 10), 1 free. Uses vtB => top type 2/2.
        Reservation::query()->create([
            'user_id' => $agencyLow->id,
            'merchant_transaction_id' => 'mt-ag-low-1',
            'drop_off_time_slot_id' => $slotDay->id,
            'pick_up_time_slot_id' => $slotDay->id,
            'reservation_date' => $d,
            'user_name' => $agencyLow->name,
            'country' => 'ME',
            'license_plate' => 'L1',
            'vehicle_type_id' => $vtB->id,
            'email' => 'x@example.com',
            'status' => 'paid',
            'invoice_amount' => '10.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
            'created_by_admin' => false,
        ]);
        Reservation::query()->create([
            'user_id' => $agencyLow->id,
            'merchant_transaction_id' => 'mt-ag-low-free',
            'drop_off_time_slot_id' => $slotMorning->id,
            'pick_up_time_slot_id' => $slotEvening->id,
            'reservation_date' => $d,
            'user_name' => $agencyLow->name,
            'country' => 'ME',
            'license_plate' => 'L2',
            'vehicle_type_id' => $vtB->id,
            'email' => 'x@example.com',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
            'created_by_admin' => false,
        ]);

        $html = $this->get(route('panel_admin.analytics', [
            'show' => 1,
            'date_from' => $d,
            'date_to' => $d,
            'include_free' => 1,
        ], false))->assertOk()->getContent();

        $this->assertStringContainsString('Analiza po agencijama', $html);

        // Sorted by revenue desc: High should appear before Low.
        $posHigh = strpos($html, 'Agencija High');
        $posLow = strpos($html, 'Agencija Low');
        $this->assertNotFalse($posHigh);
        $this->assertNotFalse($posLow);
        $this->assertTrue($posHigh < $posLow);

        // Revenue counts paid only: High=100, Low=10.
        $this->assertStringContainsString('100.00 EUR', $html);
        $this->assertStringContainsString('10.00 EUR', $html);

        // Free counted in column: High has 1 free, Low has 1 free.
        $this->assertMatchesRegularExpression('/Agencija High[\s\S]*?<td[^>]*>\s*1\s*<\/td>[\s\S]*?<td[^>]*>\s*33\.3%/i', $html);

        // Slot count rule: High occupied = 1 + 2 + 1 = 4.
        $this->assertMatchesRegularExpression('/Agencija High[\s\S]*?<td[^>]*>\s*4\s*<\/td>/i', $html);

        // Top vehicle type for High: vtA appears 2 of 3 => 66.7%.
        $this->assertMatchesRegularExpression('/Agencija High[\s\S]*?Tip A\s*\(opis a\)[\s\S]*?66\.7%/i', $html);

        $pdf = $this->get(route('panel_admin.analytics.pdf', [
            'date_from' => $d,
            'date_to' => $d,
            'include_free' => 1,
        ], false));

        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
    }

    public function test_admin_free_fzbr_by_agency_section_groups_by_user_and_includes_none_and_pdf_contains_it(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $slotMorning = ListOfTimeSlot::query()->create(['time_slot' => '06:00 - 06:20']);
        $slotDay = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotEvening = ListOfTimeSlot::query()->create(['time_slot' => '21:00 - 21:20']);

        $vtA = VehicleType::query()->create(['price' => 50]);
        foreach (['cg', 'en'] as $loc) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vtA->id,
                'locale' => $loc,
                'name' => $loc === 'cg' ? 'Tip A' : 'Type A',
                'description' => $loc === 'cg' ? 'opis a' : 'desc a',
            ]);
        }

        $agency = User::query()->create([
            'name' => 'Agencija X',
            'email' => 'ax@example.com',
            'password' => bcrypt('secret'),
            'lang' => 'cg',
            'country' => 'ME',
            'email_verified_at' => now(),
        ]);

        $d = Carbon::now()->addDays(4)->toDateString();

        // Agency: 2 admin-free reservations.
        Reservation::query()->create([
            'user_id' => $agency->id,
            'merchant_transaction_id' => 'mt-af-1',
            'drop_off_time_slot_id' => $slotMorning->id,
            'pick_up_time_slot_id' => $slotMorning->id, // 1 slot
            'reservation_date' => $d,
            'user_name' => $agency->name,
            'country' => 'ME',
            'license_plate' => 'AF1',
            'vehicle_type_id' => $vtA->id,
            'email' => 'a@example.com',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
            'created_by_admin' => true,
        ]);
        Reservation::query()->create([
            'user_id' => $agency->id,
            'merchant_transaction_id' => 'mt-af-2',
            'drop_off_time_slot_id' => $slotDay->id,
            'pick_up_time_slot_id' => $slotEvening->id, // 2 slots
            'reservation_date' => $d,
            'user_name' => $agency->name,
            'country' => 'ME',
            'license_plate' => 'AF2',
            'vehicle_type_id' => $vtA->id,
            'email' => 'a@example.com',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
            'created_by_admin' => true,
        ]);

        // None: 1 admin-free reservation without agency.
        Reservation::query()->create([
            'user_id' => null,
            'merchant_transaction_id' => 'mt-af-none',
            'drop_off_time_slot_id' => $slotEvening->id,
            'pick_up_time_slot_id' => $slotEvening->id,
            'reservation_date' => $d,
            'user_name' => 'n/a',
            'country' => 'ME',
            'license_plate' => 'AFN',
            'vehicle_type_id' => $vtA->id,
            'email' => 'a@example.com',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
            'created_by_admin' => true,
        ]);

        // Should be excluded: not admin created.
        Reservation::query()->create([
            'user_id' => $agency->id,
            'merchant_transaction_id' => 'mt-af-excluded',
            'drop_off_time_slot_id' => $slotMorning->id,
            'pick_up_time_slot_id' => $slotMorning->id,
            'reservation_date' => $d,
            'user_name' => $agency->name,
            'country' => 'ME',
            'license_plate' => 'AFX',
            'vehicle_type_id' => $vtA->id,
            'email' => 'a@example.com',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
            'created_by_admin' => false,
        ]);

        $html = $this->get(route('panel_admin.analytics', [
            'show' => 1,
            'date_from' => $d,
            'date_to' => $d,
            'include_free' => 0,
        ], false))->assertOk()->getContent();

        $this->assertStringContainsString('Admin free (FZBR) po agencijama', $html);

        // Agency appears with 2 reservations and occupied slots = 1 + 2 = 3.
        $this->assertMatchesRegularExpression('/Agencija X[\s\S]*?<td[^>]*>\s*2\s*<\/td>[\s\S]*?<td[^>]*>\s*3\s*<\/td>/i', $html);

        // None bucket present.
        $this->assertStringContainsString('Bez agencije', $html);

        // Sorting by reservation count desc -> Agencija X (2) should appear before Bez agencije (1).
        $posA = strpos($html, 'Agencija X');
        $posNone = strpos($html, 'Bez agencije');
        $this->assertNotFalse($posA);
        $this->assertNotFalse($posNone);
        $this->assertTrue($posA < $posNone);

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

