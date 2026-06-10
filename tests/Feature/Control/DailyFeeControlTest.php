<?php

namespace Tests\Feature\Control;

use App\Models\Admin;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Support\ReservationKind;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class DailyFeeControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-10 14:30:00', 'Europe/Podgorica'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_cannot_access_control_page(): void
    {
        $this->get(route('control.daily_fee.index', [], false))
            ->assertRedirect(route('control.login', [], false));
    }

    public function test_guest_cannot_submit_check(): void
    {
        $this->post(route('control.daily_fee.check', [], false), [
            'license_plate' => 'PG123AB',
        ])->assertRedirect(route('control.login', [], false));
    }

    public function test_authorized_control_user_can_open_page(): void
    {
        $admin = $this->createControlAdmin();

        $this->actingAs($admin, 'control')
            ->get(route('control.daily_fee.index', [], false))
            ->assertOk()
            ->assertSee('Kontrola dnevne naknade', false)
            ->assertSee('Registarska tablica', false)
            ->assertSee('Provjeri', false);
    }

    public function test_plate_input_is_normalized_to_uppercase(): void
    {
        $admin = $this->createControlAdmin();
        $vt = $this->createVehicleType('Autobus');
        $this->createPaidDailyFee('PG123AB', '2026-06-10', $vt);

        $this->actingAs($admin, 'control')
            ->post(route('control.daily_fee.check', [], false), [
                'license_plate' => ' pg 123 ab ',
            ])
            ->assertOk()
            ->assertSee('Plaćena dnevna naknada: DA', false)
            ->assertSee('PG123AB', false);
    }

    public function test_paid_daily_fee_for_today_returns_da(): void
    {
        $admin = $this->createControlAdmin();
        $vt = $this->createVehicleType('Minibus');
        $this->createPaidDailyFee('KO999ZZ', '2026-06-10', $vt, [
            'user_name' => 'Agencija Test',
            'email' => 'agency@test.local',
        ]);

        $this->actingAs($admin, 'control')
            ->post(route('control.daily_fee.check', [], false), [
                'license_plate' => 'KO999ZZ',
            ])
            ->assertOk()
            ->assertSee('Plaćena dnevna naknada: DA', false)
            ->assertSee('Agencija Test', false)
            ->assertSee('agency@test.local', false)
            ->assertSee('Minibus', false);
    }

    public function test_paid_daily_fee_for_another_day_returns_ne(): void
    {
        $admin = $this->createControlAdmin();
        $vt = $this->createVehicleType('Van');
        $this->createPaidDailyFee('PG111AA', '2026-06-11', $vt);

        $this->actingAs($admin, 'control')
            ->post(route('control.daily_fee.check', [], false), [
                'license_plate' => 'PG111AA',
            ])
            ->assertOk()
            ->assertSee('Plaćena dnevna naknada: NE', false)
            ->assertSee('PG111AA', false)
            ->assertSee('10.06.2026', false);
    }

    public function test_time_slots_reservation_for_today_returns_ne(): void
    {
        $admin = $this->createControlAdmin();
        $vt = $this->createVehicleType('Bus');
        $drop = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $pick = ListOfTimeSlot::query()->create(['time_slot' => '18:00 - 18:20']);

        Reservation::query()->create([
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'reservation_date' => '2026-06-10',
            'user_name' => 'Slot User',
            'country' => 'ME',
            'license_plate' => 'PGSLOT1',
            'vehicle_type_id' => $vt->id,
            'email' => 'slot@test.local',
            'status' => 'paid',
            'invoice_amount' => '40.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'control')
            ->post(route('control.daily_fee.check', [], false), [
                'license_plate' => 'PGSLOT1',
            ])
            ->assertOk()
            ->assertSee('Plaćena dnevna naknada: NE', false);
    }

    public function test_unpaid_daily_fee_reservation_returns_ne(): void
    {
        $admin = $this->createControlAdmin();
        $vt = $this->createVehicleType('Bus');

        Reservation::query()->create([
            'reservation_kind' => ReservationKind::DAILY_TICKET,
            'drop_off_time_slot_id' => null,
            'pick_up_time_slot_id' => null,
            'reservation_date' => '2026-06-10',
            'user_name' => 'Free Daily',
            'country' => 'ME',
            'license_plate' => 'PGFREE1',
            'vehicle_type_id' => $vt->id,
            'email' => 'free@test.local',
            'status' => 'free',
            'invoice_amount' => '0.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        $this->actingAs($admin, 'control')
            ->post(route('control.daily_fee.check', [], false), [
                'license_plate' => 'PGFREE1',
            ])
            ->assertOk()
            ->assertSee('Plaćena dnevna naknada: NE', false);
    }

    public function test_multiple_paid_daily_fees_for_same_plate_and_day_are_listed(): void
    {
        $admin = $this->createControlAdmin();
        $vt = $this->createVehicleType('Bus');

        $this->createPaidDailyFee('PGMULTI', '2026-06-10', $vt, [
            'user_name' => 'Agencija A',
            'email' => 'a@test.local',
            'created_at' => Carbon::parse('2026-06-10 09:00:00', 'Europe/Podgorica'),
        ]);
        $this->createPaidDailyFee('PGMULTI', '2026-06-10', $vt, [
            'user_name' => 'Agencija B',
            'email' => 'b@test.local',
            'created_at' => Carbon::parse('2026-06-10 11:00:00', 'Europe/Podgorica'),
        ]);

        $html = $this->actingAs($admin, 'control')
            ->post(route('control.daily_fee.check', [], false), [
                'license_plate' => 'PGMULTI',
            ])
            ->assertOk()
            ->assertSee('Plaćena dnevna naknada: DA', false)
            ->getContent();

        $this->assertStringContainsString('Agencija A', $html);
        $this->assertStringContainsString('Agencija B', $html);
    }

    public function test_check_does_not_trigger_payment_fiscal_or_email_side_effects(): void
    {
        Mail::fake();
        Queue::fake();

        $admin = $this->createControlAdmin();
        $vt = $this->createVehicleType('Bus');
        $this->createPaidDailyFee('PGSIDE1', '2026-06-10', $vt);
        $countAfterSetup = Reservation::query()->count();

        $this->actingAs($admin, 'control')
            ->post(route('control.daily_fee.check', [], false), [
                'license_plate' => 'PGSIDE1',
            ])
            ->assertOk();

        $this->assertSame($countAfterSetup, Reservation::query()->count());
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    private function createControlAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'control_dn',
            'email' => 'control-dn@test.local',
            'password' => bcrypt('secret'),
            'control_access' => true,
        ]);
    }

    private function createVehicleType(string $name): VehicleType
    {
        $vt = VehicleType::query()->create(['price' => '40.00']);
        foreach (['cg', 'en'] as $locale) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $locale,
                'name' => $name,
                'description' => null,
            ]);
        }

        return $vt;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPaidDailyFee(string $plate, string $date, VehicleType $vt, array $overrides = []): Reservation
    {
        return Reservation::query()->create(array_merge([
            'reservation_kind' => ReservationKind::DAILY_TICKET,
            'drop_off_time_slot_id' => null,
            'pick_up_time_slot_id' => null,
            'reservation_date' => $date,
            'user_name' => 'Agencija',
            'country' => 'ME',
            'license_plate' => $plate,
            'vehicle_type_id' => $vt->id,
            'email' => 'agency@example.test',
            'status' => 'paid',
            'invoice_amount' => '40.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ], $overrides));
    }
}
