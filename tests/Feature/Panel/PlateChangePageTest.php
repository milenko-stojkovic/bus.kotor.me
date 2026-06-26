<?php

namespace Tests\Feature\Panel;

use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\Pdf\FreeReservationPdfGenerator;
use App\Services\Pdf\PaidInvoicePdfGenerator;
use App\Services\Reservation\PanelReservationListService;
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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
            ->assertDontSee('Promjena tablice za dnevnu naknadu nije dostupna za tekući dan.', false);
    }

    public function test_daily_fee_for_future_date_shows_plate_change_action(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', PanelReservationListService::OPERATIONS_TIMEZONE));

        [$user, $reservation, $replacement] = $this->dailyFeeReservationWithSpareVehicle('2026-07-15');

        $this->actingAs($user)
            ->get('/locale/cg')
            ->assertRedirect();

        $this->get(route('panel.upcoming', [], false))
            ->assertOk()
            ->assertSee('Promijeni tablicu', false)
            ->assertDontSee('Promjena tablice za dnevnu naknadu nije dostupna za tekući dan.', false);
    }

    public function test_daily_fee_for_today_hides_plate_change_action(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 14:00:00', PanelReservationListService::OPERATIONS_TIMEZONE));

        [$user] = $this->dailyFeeReservationWithSpareVehicle('2026-07-10');

        $this->actingAs($user)
            ->get('/locale/cg')
            ->assertRedirect();

        $this->get(route('panel.upcoming', [], false))
            ->assertOk()
            ->assertSee('Promjena tablice za dnevnu naknadu nije dostupna za tekući dan.', false)
            ->assertDontSee('Promijeni tablicu', false);
    }

    public function test_daily_fee_for_past_date_is_not_on_plate_change_page(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', PanelReservationListService::OPERATIONS_TIMEZONE));

        [$user, $reservation] = $this->dailyFeeReservationWithSpareVehicle('2026-07-09');

        $this->actingAs($user)
            ->get(route('panel.upcoming', [], false))
            ->assertOk()
            ->assertDontSee($reservation->license_plate, false);
    }

    public function test_patch_daily_fee_future_succeeds_with_valid_agency_vehicle(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', PanelReservationListService::OPERATIONS_TIMEZONE));

        [$user, $reservation, $replacement] = $this->dailyFeeReservationWithSpareVehicle('2026-07-15');
        $paidTypeId = (int) $reservation->vehicle_type_id;
        $invoiceAmount = $reservation->invoice_amount;

        $this->actingAs($user)
            ->from(route('panel.upcoming', [], false))
            ->patch(route('panel.reservations.vehicle', $reservation->id, false), [
                'vehicle_id' => $replacement->id,
            ])
            ->assertRedirect(route('panel.upcoming', [], false));

        $reservation->refresh();
        $this->assertSame($replacement->license_plate, $reservation->license_plate);
        $this->assertSame((int) $replacement->id, (int) $reservation->vehicle_id);
        $this->assertSame($paidTypeId, (int) $reservation->vehicle_type_id);
        $this->assertSame($invoiceAmount, $reservation->invoice_amount);
    }

    public function test_patch_daily_fee_today_fails(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', PanelReservationListService::OPERATIONS_TIMEZONE));

        [$user, $reservation, $replacement] = $this->dailyFeeReservationWithSpareVehicle('2026-07-10');

        $this->actingAs($user)
            ->from(route('panel.upcoming', [], false))
            ->patch(route('panel.reservations.vehicle', $reservation->id, false), [
                'vehicle_id' => $replacement->id,
            ])
            ->assertSessionHasErrors('vehicle_id');

        $reservation->refresh();
        $this->assertNotSame($replacement->license_plate, $reservation->license_plate);
    }

    public function test_patch_daily_fee_past_fails(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', PanelReservationListService::OPERATIONS_TIMEZONE));

        [$user, $reservation, $replacement] = $this->dailyFeeReservationWithSpareVehicle('2026-07-09');

        $this->actingAs($user)
            ->from(route('panel.upcoming', [], false))
            ->patch(route('panel.reservations.vehicle', $reservation->id, false), [
                'vehicle_id' => $replacement->id,
            ])
            ->assertSessionHasErrors('vehicle_id');

        $reservation->refresh();
        $this->assertNotSame($replacement->license_plate, $reservation->license_plate);
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

    public function test_upcoming_paid_reservation_shows_pdf_link_to_agency_invoice_route(): void
    {
        [$user, $reservation] = $this->upcomingTimeSlotsReservationWithSpareVehicle();

        $this->actingAs($user)
            ->get('/locale/cg')
            ->assertRedirect();

        $this->get(route('panel.upcoming', [], false))
            ->assertOk()
            ->assertSee(route('panel.reservations.invoice.view', ['id' => $reservation->id], false), false)
            ->assertSee('PDF', false)
            ->assertSee('Promijeni tablicu', false);
    }

    public function test_upcoming_free_reservation_shows_pdf_link(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', PanelReservationListService::OPERATIONS_TIMEZONE));

        $user = User::factory()->create();
        $vt = $this->busType();
        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);

        $reservation = Reservation::query()->create([
            'user_id' => $user->id,
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotB->id,
            'reservation_date' => '2026-07-15',
            'user_name' => 'Agency',
            'country' => 'ME',
            'license_plate' => 'FR100',
            'vehicle_type_id' => $vt->id,
            'email' => $user->email,
            'status' => 'free',
            'invoice_amount' => 0,
        ]);

        $html = $this->actingAs($user)
            ->get(route('panel.upcoming', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            route('panel.reservations.invoice.view', ['id' => $reservation->id], false),
            $html,
        );
    }

    public function test_upcoming_paid_pdf_download_uses_invoice_filename(): void
    {
        $this->mockPaidPdf();
        [$user, $reservation] = $this->upcomingTimeSlotsReservationWithSpareVehicle();

        $response = $this->actingAs($user)
            ->get(route('panel.reservations.invoice.view', ['id' => $reservation->id], false));

        $response->assertOk();
        $this->assertStringContainsString(
            'invoice-'.$reservation->id.'-'.$reservation->reservation_date->format('Y-m-d').'.pdf',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_upcoming_free_pdf_download_uses_free_confirmation_filename(): void
    {
        $this->mockFreePdf();
        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', PanelReservationListService::OPERATIONS_TIMEZONE));

        $user = User::factory()->create();
        $vt = $this->busType();
        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);

        $reservation = Reservation::query()->create([
            'user_id' => $user->id,
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotB->id,
            'reservation_date' => '2026-07-15',
            'user_name' => 'Agency',
            'country' => 'ME',
            'license_plate' => 'FR200',
            'vehicle_type_id' => $vt->id,
            'email' => $user->email,
            'status' => 'free',
            'invoice_amount' => 0,
        ]);

        $response = $this->actingAs($user)
            ->get(route('panel.reservations.invoice.view', ['id' => $reservation->id], false));

        $response->assertOk();
        $this->assertStringContainsString(
            'free-confirmation-'.$reservation->id.'-2026-07-15.pdf',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_agency_cannot_download_another_agencys_reservation_pdf_from_upcoming_context(): void
    {
        [$owner] = $this->upcomingTimeSlotsReservationWithSpareVehicle();
        $other = User::factory()->create();

        $reservation = Reservation::query()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($other)
            ->get(route('panel.reservations.invoice.view', ['id' => $reservation->id], false))
            ->assertNotFound();
    }

    public function test_daily_fee_today_still_shows_pdf_even_when_plate_change_hidden(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 14:00:00', PanelReservationListService::OPERATIONS_TIMEZONE));

        [$user, $reservation] = $this->dailyFeeReservationWithSpareVehicle('2026-07-10');

        $this->actingAs($user)
            ->get('/locale/cg')
            ->assertRedirect();

        $this->get(route('panel.upcoming', [], false))
            ->assertOk()
            ->assertSee(route('panel.reservations.invoice.view', ['id' => $reservation->id], false), false)
            ->assertSee('Promjena tablice za dnevnu naknadu nije dostupna za tekući dan.', false)
            ->assertDontSee('Promijeni tablicu', false);
    }

    private function mockPaidPdf(): void
    {
        $this->app->instance(PaidInvoicePdfGenerator::class, new class extends PaidInvoicePdfGenerator {
            public function renderBinary(Reservation $reservation, bool $isFiscal): string
            {
                return '%PDF-1.4';
            }
        });
    }

    private function mockFreePdf(): void
    {
        $this->app->instance(FreeReservationPdfGenerator::class, new class extends FreeReservationPdfGenerator {
            public function renderBinary(Reservation $reservation): string
            {
                return '%PDF-1.4';
            }
        });
    }

    /**
     * @return array{0: User, 1: Reservation, 2: Vehicle}
     */
    private function dailyFeeReservationWithSpareVehicle(string $date): array
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
            'reservation_date' => $date,
            'user_name' => 'Agency',
            'country' => 'ME',
            'license_plate' => $vehicleA->license_plate,
            'vehicle_type_id' => $vt->id,
            'email' => $user->email,
            'status' => 'paid',
            'invoice_amount' => 10,
        ]);

        return [$user, $reservation, $vehicleB];
    }

    /**
     * @return array{0: User, 1: Reservation, 2: Vehicle}
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
