<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelReportsTest extends TestCase
{
    use RefreshDatabase;

    private function seedAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'reportsadmin',
            'email' => 'reports-admin@example.com',
            'password' => bcrypt('secret-password-rep'),
            'control_access' => false,
            'admin_access' => true,
        ]);
    }

    public function test_reports_page_renders(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.reports', [], false))
            ->assertOk()
            ->assertSee('Izveštaji', false);
    }

    public function test_reports_pdf_exports_work_for_all_kinds(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $slotMorning = ListOfTimeSlot::query()->create(['time_slot' => '06:00 - 06:20']);
        $slotDay = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);

        $types = [];
        foreach ([10, 20, 30, 40] as $price) {
            $types[] = VehicleType::query()->create(['price' => $price]);
        }
        foreach ($types as $i => $vt) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => 'cg',
                'name' => 'VT'.($i + 1),
                'description' => 'd'.($i + 1),
            ]);
        }

        $created = Carbon::now()->subDays(5)->setTime(12, 0, 0);
        $dCreated = $created->toDateString();

        // Payment report uses created_at date part and paid only.
        $rPay = Reservation::query()->create([
            'merchant_transaction_id' => 'mt-rep-pay-1',
            'drop_off_time_slot_id' => $slotDay->id,
            'pick_up_time_slot_id' => $slotDay->id,
            'reservation_date' => Carbon::now()->addDays(2)->toDateString(),
            'user_name' => 'P',
            'country' => 'ME',
            'license_plate' => 'KO1',
            'vehicle_type_id' => $types[0]->id,
            'email' => 'p@example.com',
            'status' => 'paid',
            'invoice_amount' => '50.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);
        $rPay->forceFill(['created_at' => $created, 'updated_at' => $created])->save();

        // Realization/vehicle-type report uses realized reservations by reservation_date.
        $realizedDate = Carbon::now()->subDays(2)->toDateString(); // always realized (past day)
        DailyParkingData::query()->create([
            'date' => $realizedDate,
            'time_slot_id' => $slotMorning->id,
            'capacity' => 5,
            'reserved' => 0,
            'pending' => 0,
            'is_blocked' => false,
        ]);
        DailyParkingData::query()->create([
            'date' => $realizedDate,
            'time_slot_id' => $slotDay->id,
            'capacity' => 5,
            'reserved' => 0,
            'pending' => 0,
            'is_blocked' => false,
        ]);

        $rReal = Reservation::query()->create([
            'merchant_transaction_id' => 'mt-rep-real-1',
            'drop_off_time_slot_id' => $slotMorning->id,
            'pick_up_time_slot_id' => $slotDay->id,
            'reservation_date' => $realizedDate,
            'user_name' => 'R',
            'country' => 'ME',
            'license_plate' => 'KO2',
            'vehicle_type_id' => $types[1]->id,
            'email' => 'r@example.com',
            'status' => 'paid',
            'invoice_amount' => '80.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);
        $rReal->forceFill(['created_at' => $created, 'updated_at' => $created])->save();

        // Ensure created_at bounds allow selecting realizedDate (bounds are based on created_at).
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-rep-bounds-max',
            'drop_off_time_slot_id' => $slotDay->id,
            'pick_up_time_slot_id' => $slotDay->id,
            'reservation_date' => Carbon::now()->addDays(3)->toDateString(),
            'user_name' => 'B',
            'country' => 'ME',
            'license_plate' => 'KO9',
            'vehicle_type_id' => $types[2]->id,
            'email' => 'bounds@example.com',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        // By payment (daily)
        $pdf1 = $this->get(route('panel_admin.reports.pdf', [
            'when' => 'daily',
            'kind' => 'by_payment',
            'date' => $dCreated,
        ], false));
        $pdf1->assertOk();
        $this->assertSame('application/pdf', $pdf1->headers->get('Content-Type'));

        // By realization (period)
        $pdf2 = $this->get(route('panel_admin.reports.pdf', [
            'when' => 'period',
            'kind' => 'by_realization',
            'date_from' => $realizedDate,
            'date_to' => $realizedDate,
        ], false));
        $pdf2->assertOk();
        $this->assertSame('application/pdf', $pdf2->headers->get('Content-Type'));

        // By vehicle type (period)
        $pdf3 = $this->get(route('panel_admin.reports.pdf', [
            'when' => 'period',
            'kind' => 'by_vehicle_type',
            'date_from' => $realizedDate,
            'date_to' => $realizedDate,
        ], false));
        $pdf3->assertOk();
        $this->assertSame('application/pdf', $pdf3->headers->get('Content-Type'));

        // Yearly should be allowed even if bounds are within the year (overlap semantics).
        $pdf4 = $this->get(route('panel_admin.reports.pdf', [
            'when' => 'yearly',
            'kind' => 'by_payment',
            'year' => (int) Carbon::parse($dCreated)->format('Y'),
        ], false));
        $pdf4->assertOk();
        $this->assertSame('application/pdf', $pdf4->headers->get('Content-Type'));
    }
}

