<?php

namespace Tests\Feature\Reservation;

use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\AdminPanel\Analytics\AdminAnalyticsService;
use App\Services\Pdf\KotorPdfAssets;
use App\Services\Pdf\PaidInvoicePdfGenerator;
use App\Services\Reservation\ReservationVehicleEligibilityService;
use App\Support\ReservationKind;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

final class ReservationVehicleEligibilityBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ReservationVehicleEligibilityService::clearCache();
    }

    protected function tearDown(): void
    {
        ReservationVehicleEligibilityService::clearCache();
        parent::tearDown();
    }

    public function test_resolves_daily_fee_only_vehicle_type_ids_from_translations(): void
    {
        $limo = $this->createLimoPassengerType();
        $bus = $this->createBusType();

        $ids = app(ReservationVehicleEligibilityService::class)->dailyFeeOnlyVehicleTypeIds();

        $this->assertContains((int) $limo->id, $ids);
        $this->assertNotContains((int) $bus->id, $ids);
    }

    public function test_guest_termini_vehicle_list_excludes_limo_passenger_categories(): void
    {
        $limo = $this->createLimoPassengerType();
        $bus = $this->createBusType();

        $this->get('/locale/cg')->assertRedirect();

        $html = $this->get('/guest/reserve')->assertOk()->getContent();

        $this->assertStringContainsString($bus->formatLabel('cg', 'EUR'), $html);
        $this->assertStringNotContainsString($limo->formatLabel('cg', 'EUR'), $html);
    }

    public function test_agency_termini_vehicle_list_excludes_limo_passenger_categories(): void
    {
        [$user, $limoVehicle, $busVehicle] = $this->agencyWithLimoAndBusVehicles();

        $html = $this->actingAs($user)
            ->get(route('panel.reservations', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($busVehicle->license_plate, $html);
        $this->assertStringNotContainsString($limoVehicle->license_plate, $html);
    }

    public function test_agency_daily_fee_vehicle_list_includes_limo_passenger_categories(): void
    {
        [$user, $limoVehicle] = $this->agencyWithLimoAndBusVehicles();

        $html = $this->actingAs($user)
            ->get(route('panel.reservations', ['reservation_kind' => ReservationKind::DAILY_TICKET], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($limoVehicle->license_plate, $html);
    }

    public function test_guest_forged_post_with_limo_category_and_time_slots_fails_validation(): void
    {
        [$drop, $pick] = $this->seedPaidSlots();
        $limo = $this->createLimoPassengerType();
        $date = now()->addDays(2)->toDateString();

        $this->from('/guest/reserve')
            ->post(route('checkout.store', [], false), [
                'reservation_date' => $date,
                'drop_off_time_slot_id' => $drop->id,
                'pick_up_time_slot_id' => $pick->id,
                'vehicle_type_id' => $limo->id,
                'name' => 'Guest Test',
                'country' => 'ME',
                'license_plate' => 'PGTEST1',
                'email' => 'guest@test.local',
                'accept_terms' => 1,
                'accept_privacy' => 1,
            ])
            ->assertSessionHasErrors('vehicle_type_id');
    }

    public function test_agency_forged_post_with_limo_vehicle_and_time_slots_fails_validation(): void
    {
        [$user, $limoVehicle] = $this->agencyWithLimoAndBusVehicles();
        [$drop, $pick] = $this->seedPaidSlots();
        $date = now()->addDays(2)->toDateString();

        $this->actingAs($user)
            ->from(route('panel.reservations', [], false))
            ->post(route('checkout.store', [], false), [
                'auth_panel_booking' => 1,
                'reservation_kind' => ReservationKind::TIME_SLOTS,
                'reservation_date' => $date,
                'drop_off_time_slot_id' => $drop->id,
                'pick_up_time_slot_id' => $pick->id,
                'vehicle_id' => $limoVehicle->id,
                'payment_method' => 'card',
                'accept_terms' => 1,
                'accept_privacy' => 1,
            ])
            ->assertSessionHasErrors('vehicle_id');
    }

    public function test_historical_time_slots_reservation_with_limo_category_still_renders_in_admin_search(): void
    {
        $limo = $this->createLimoPassengerType();
        [$drop, $pick] = $this->seedPaidSlots();

        $reservation = Reservation::query()->create([
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'reservation_date' => now()->addDays(5)->toDateString(),
            'user_name' => 'Historic Limo Agency',
            'country' => 'ME',
            'license_plate' => 'PGHIST1',
            'vehicle_type_id' => $limo->id,
            'email' => 'hist@test.local',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $admin = \App\Models\Admin::query()->create([
            'username' => 'panel_admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('secret'),
            'admin_access' => true,
            'control_access' => false,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get(route('panel_admin.reservations', ['license_plate' => 'PGHIST'], false))
            ->assertOk()
            ->assertSee('PGHIST1', false)
            ->assertSee((string) $reservation->id, false);
    }

    public function test_analytics_still_counts_historical_time_slots_limo_reservation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'Europe/Podgorica'));

        $limo = $this->createLimoPassengerType();
        [$drop, $pick] = $this->seedPaidSlots();

        Reservation::query()->create([
            'merchant_transaction_id' => 'mt-hist-limo',
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'reservation_date' => '2026-06-15',
            'user_name' => 'Historic',
            'country' => 'ME',
            'license_plate' => 'PGOLD1',
            'vehicle_type_id' => $limo->id,
            'email' => 'old@test.local',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $dataset = app(AdminAnalyticsService::class)->build('2026-06-15', '2026-06-15', false);

        $this->assertSame(1, (int) ($dataset['kpi']['paid_reservations'] ?? 0));
        $this->assertSame(15.0, (float) ($dataset['kpi']['revenue_reservations'] ?? 0));

        Carbon::setTestNow();
    }

    public function test_pdf_generation_still_works_for_historical_limo_time_slots_reservation(): void
    {
        $limo = $this->createLimoPassengerType();
        [$drop, $pick] = $this->seedPaidSlots();

        $reservation = Reservation::query()->create([
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'reservation_date' => now()->addDays(3)->toDateString(),
            'user_name' => 'PDF Historic',
            'country' => 'ME',
            'license_plate' => 'PGPDF1',
            'vehicle_type_id' => $limo->id,
            'email' => 'pdf@test.local',
            'preferred_locale' => 'cg',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $reservation->loadMissing(['vehicleType.translations', 'dropOffTimeSlot', 'pickUpTimeSlot']);

        $html = View::make('pdf.paid-invoice', [
            'reservation' => $reservation,
            'isFiscal' => false,
            'isDailyTicket' => false,
            'validityDateDisplay' => null,
            'logoDataUri' => KotorPdfAssets::logoDataUri(),
            'qrDataUri' => null,
            'countryDisplay' => KotorPdfAssets::countryDisplayCg('ME'),
            'vehicleLine' => $limo->getTranslatedName('cg'),
            'unitPrice' => 15.0,
            'fiscalDateTime' => Carbon::now(),
            'internalNumber' => null,
            'nonFiscalNote' => PaidInvoicePdfGenerator::nonFiscalNoteFor($reservation),
        ])->render();

        $this->assertStringContainsString('PGPDF1', $html);
        $this->assertStringContainsString('Putničko vozilo', $html);
    }

    private function createLimoPassengerType(): VehicleType
    {
        $vt = VehicleType::query()->create(['price' => 15.00]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'Putničko vozilo',
            'description' => '4+1 do 7+1 sjedišta',
        ]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'en',
            'name' => 'Personal vehicle',
            'description' => 'Passenger car (4+1 to 7+1 seats)',
        ]);

        return $vt->load('translations');
    }

    private function createBusType(): VehicleType
    {
        $vt = VehicleType::query()->create(['price' => 40.00]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'Srednji autobus',
            'description' => '9–23 sjedišta',
        ]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'en',
            'name' => 'Medium bus',
            'description' => '9–23 seats',
        ]);

        return $vt->load('translations');
    }

    /**
     * @return array{0: User, 1: Vehicle, 2: Vehicle}
     */
    private function agencyWithLimoAndBusVehicles(): array
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'country' => 'ME']);
        $limo = $this->createLimoPassengerType();
        $bus = $this->createBusType();

        $limoVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'license_plate' => 'LIMO001',
            'vehicle_type_id' => $limo->id,
        ]);
        $busVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'license_plate' => 'BUS001',
            'vehicle_type_id' => $bus->id,
        ]);

        return [$user, $limoVehicle, $busVehicle];
    }

    /**
     * @return array{0: ListOfTimeSlot, 1: ListOfTimeSlot}
     */
    private function seedPaidSlots(): array
    {
        $drop = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $pick = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $date = now()->addDays(2)->toDateString();

        foreach ([$drop, $pick] as $slot) {
            DailyParkingData::query()->create([
                'date' => $date,
                'time_slot_id' => $slot->id,
                'capacity' => 9,
                'reserved' => 0,
                'pending' => 0,
                'is_blocked' => false,
            ]);
        }

        return [$drop, $pick];
    }
}
