<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\AdminPanel\Reports\AdminReportsService;
use App\Services\Reservation\ReservationVehicleEligibilityService;
use App\Support\ReservationKind;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelReservationTypeReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ReservationVehicleEligibilityService::clearCache();
    }

    private function seedAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'restypeadmin',
            'email' => 'restype-admin@example.com',
            'password' => bcrypt('secret-password-restype'),
            'control_access' => false,
            'admin_access' => true,
        ]);
    }

    public function test_reports_dropdown_includes_by_reservation_type(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $html = $this->get(route('panel_admin.reports', [], false))->assertOk()->getContent();
        $this->assertStringContainsString('Po tipu rezervacije', $html);
        $this->assertStringContainsString('value="by_reservation_type"', $html);
    }

    public function test_by_reservation_type_groups_paid_reservations_by_kind_and_vehicle_type(): void
    {
        $slot = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $limoPassenger = $this->createLimoPassengerType();
        $minibus = $this->createMinibusType();
        $bus = $this->createMediumBusType();

        $reportDate = '2026-06-15';
        $from = Carbon::parse($reportDate)->startOfDay();
        $to = $from->copy();

        // Termini (paid) — 50 + 30
        $this->createPaidReservation([
            'merchant_transaction_id' => 'mt-ts-1',
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => $reportDate,
            'vehicle_type_id' => $bus->id,
            'invoice_amount' => '50.00',
        ]);
        $this->createPaidReservation([
            'merchant_transaction_id' => 'mt-ts-2',
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => $reportDate,
            'vehicle_type_id' => $bus->id,
            'invoice_amount' => '30.00',
        ]);

        // Daily fee Limo — passenger + minibus
        $this->createPaidReservation([
            'merchant_transaction_id' => 'mt-dn-limo-1',
            'reservation_kind' => ReservationKind::DAILY_TICKET,
            'drop_off_time_slot_id' => null,
            'pick_up_time_slot_id' => null,
            'reservation_date' => $reportDate,
            'vehicle_type_id' => $limoPassenger->id,
            'invoice_amount' => '15.00',
        ]);
        $this->createPaidReservation([
            'merchant_transaction_id' => 'mt-dn-limo-2',
            'reservation_kind' => ReservationKind::DAILY_TICKET,
            'drop_off_time_slot_id' => null,
            'pick_up_time_slot_id' => null,
            'reservation_date' => $reportDate,
            'vehicle_type_id' => $minibus->id,
            'invoice_amount' => '25.00',
        ]);

        // Daily fee Autobusi
        $this->createPaidReservation([
            'merchant_transaction_id' => 'mt-dn-bus-1',
            'reservation_kind' => ReservationKind::DAILY_TICKET,
            'drop_off_time_slot_id' => null,
            'pick_up_time_slot_id' => null,
            'reservation_date' => $reportDate,
            'vehicle_type_id' => $bus->id,
            'invoice_amount' => '40.00',
        ]);

        // Excluded: free
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-free-1',
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => $reportDate,
            'user_name' => 'F',
            'country' => 'ME',
            'license_plate' => 'FREE1',
            'vehicle_type_id' => $bus->id,
            'email' => 'free@example.com',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        // Excluded: pending
        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-pending-1',
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => $reportDate,
            'user_name' => 'P',
            'country' => 'ME',
            'license_plate' => 'PEND1',
            'vehicle_type_id' => $bus->id,
            'email' => 'pending@example.com',
            'status' => 'pending',
            'invoice_amount' => '99.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        // Excluded: outside date range
        $this->createPaidReservation([
            'merchant_transaction_id' => 'mt-out-range',
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $slot->id,
            'pick_up_time_slot_id' => $slot->id,
            'reservation_date' => '2026-06-20',
            'vehicle_type_id' => $bus->id,
            'invoice_amount' => '100.00',
        ]);

        $data = app(AdminReportsService::class)->byReservationType($from, $to);

        $rowByLabel = [];
        foreach ($data['rows'] as $row) {
            $rowByLabel[$row['label']] = $row;
        }

        $this->assertSame(2, $rowByLabel['Termini']['count']);
        $this->assertSame(80.0, (float) $rowByLabel['Termini']['revenue_eur']);

        $this->assertSame(2, $rowByLabel['Dnevna naknada — Limo']['count']);
        $this->assertSame(40.0, (float) $rowByLabel['Dnevna naknada — Limo']['revenue_eur']);

        $this->assertSame(1, $rowByLabel['Dnevna naknada — Autobusi']['count']);
        $this->assertSame(40.0, (float) $rowByLabel['Dnevna naknada — Autobusi']['revenue_eur']);

        $this->assertSame(3, $rowByLabel['Dnevna naknada ukupno']['count']);
        $this->assertSame(80.0, (float) $rowByLabel['Dnevna naknada ukupno']['revenue_eur']);

        $this->assertSame(5, $rowByLabel['Ukupno']['count']);
        $this->assertSame(160.0, (float) $rowByLabel['Ukupno']['revenue_eur']);
        $this->assertSame(5, $data['total_count']);
        $this->assertSame(160.0, (float) $data['total_revenue_eur']);
    }

    public function test_by_reservation_type_pdf_export_works(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $pdf = $this->get(route('panel_admin.reports.pdf', [
            'when' => 'daily',
            'kind' => 'by_reservation_type',
            'date' => Carbon::now()->toDateString(),
        ], false));

        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPaidReservation(array $overrides): Reservation
    {
        return Reservation::query()->create(array_merge([
            'user_name' => 'Test',
            'country' => 'ME',
            'license_plate' => 'KO-'.uniqid(),
            'email' => 'paid@example.com',
            'status' => 'paid',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ], $overrides));
    }

    private function createLimoPassengerType(): VehicleType
    {
        $vt = VehicleType::query()->create(['price' => '15.00']);
        foreach (['cg', 'en'] as $locale) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $locale,
                'name' => $locale === 'cg' ? 'Putničko vozilo' : 'Personal vehicle',
                'description' => $locale === 'cg' ? '4+1 do 7+1 sjedišta' : 'Passenger car (4+1 to 7+1 seats)',
            ]);
        }

        return $vt;
    }

    private function createMinibusType(): VehicleType
    {
        $vt = VehicleType::query()->create(['price' => '25.00']);
        foreach (['cg', 'en'] as $locale) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $locale,
                'name' => 'Mini bus',
                'description' => $locale === 'cg' ? 'Mini bus (8+1 sjedište)' : 'Mini bus (8+1 seats)',
            ]);
        }

        return $vt;
    }

    private function createMediumBusType(): VehicleType
    {
        $vt = VehicleType::query()->create(['price' => '40.00']);
        foreach (['cg', 'en'] as $locale) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $locale,
                'name' => $locale === 'cg' ? 'Srednji autobus' : 'Medium bus',
                'description' => $locale === 'cg' ? 'Autobus (9–23 sjedišta)' : 'Bus (9–23 seats)',
            ]);
        }

        return $vt;
    }
}
