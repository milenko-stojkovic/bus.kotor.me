<?php

namespace Tests\Feature\Guest;

use App\Models\AdminAlert;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\TempData;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\AdminFiscalizationAlertService;
use App\Services\Payment\PaymentSuccessHandler;
use App\Services\Reservation\DuplicateReservationAttemptService;
use App\Support\ReservationKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

final class GuestPaidLowerCategoryAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @return array{low: VehicleType, high: VehicleType, slotA: ListOfTimeSlot, slotB: ListOfTimeSlot} */
    private function seedFixtures(): array
    {
        $low = VehicleType::query()->create(['price' => 15.00]);
        $high = VehicleType::query()->create(['price' => 40.00]);

        foreach ([$low, $high] as $type) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $type->id,
                'locale' => 'cg',
                'name' => 'Type '.$type->id,
                'description' => null,
            ]);
        }

        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);

        return compact('low', 'high', 'slotA', 'slotB');
    }

    private function createGuestTempData(
        array $fixtures,
        string $plate,
        int $vehicleTypeId,
        string $merchantTransactionId = 'mt-guest-new',
    ): TempData {
        return TempData::query()->create([
            'merchant_transaction_id' => $merchantTransactionId,
            'retry_token' => 'retry-'.$merchantTransactionId,
            'user_id' => null,
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $fixtures['slotA']->id,
            'pick_up_time_slot_id' => $fixtures['slotB']->id,
            'reservation_date' => '2026-07-01',
            'user_name' => 'Guest User',
            'country' => 'ME',
            'license_plate' => $plate,
            'vehicle_type_id' => $vehicleTypeId,
            'email' => 'guest@example.com',
            'preferred_locale' => 'cg',
            'status' => TempData::STATUS_PENDING,
            'invoice_amount_snapshot' => 15.00,
        ]);
    }

    public function test_guest_paid_lower_category_than_historical_paid_creates_alert_and_email(): void
    {
        $fixtures = $this->seedFixtures();
        $plate = 'PG123AB';

        $fiscalAlerts = Mockery::mock(AdminFiscalizationAlertService::class)->makePartial();
        $fiscalAlerts->shouldReceive('notify')
            ->once()
            ->with(
                '[Kotor Bus] Guest paid reservation: lower vehicle category than historical paid',
                Mockery::type('string'),
                Mockery::on(function (array $context): bool {
                    return ($context['alert_type'] ?? '') === 'guest_paid_lower_category_than_history'
                        && isset($context['reservation_id'], $context['historical_reservation_id']);
                }),
            );
        $this->app->instance(AdminFiscalizationAlertService::class, $fiscalAlerts);

        Reservation::query()->create([
            'user_id' => null,
            'merchant_transaction_id' => 'mt-hist-high',
            'drop_off_time_slot_id' => $fixtures['slotA']->id,
            'pick_up_time_slot_id' => $fixtures['slotB']->id,
            'reservation_date' => '2026-06-01',
            'user_name' => 'Old Guest',
            'country' => 'ME',
            'license_plate' => DuplicateReservationAttemptService::normalizeLicensePlate($plate),
            'vehicle_type_id' => $fixtures['high']->id,
            'email' => 'old@example.com',
            'preferred_locale' => 'cg',
            'status' => 'paid',
            'invoice_amount' => 40.00,
        ]);

        $temp = $this->createGuestTempData($fixtures, $plate, (int) $fixtures['low']->id);

        $created = app(PaymentSuccessHandler::class)->handle($temp, ['source' => 'test'], true, true);

        $this->assertTrue($created);
        $this->assertSame(2, Reservation::query()->where('status', 'paid')->count());

        $this->assertDatabaseHas('admin_alerts', [
            'type' => 'guest_paid_lower_category_than_history',
            'status' => AdminAlert::STATUS_UNREAD,
        ]);
    }

    public function test_guest_paid_same_or_higher_category_does_not_alert(): void
    {
        Mail::fake();
        $fixtures = $this->seedFixtures();
        $plate = 'PG999ZZ';

        Reservation::query()->create([
            'user_id' => null,
            'merchant_transaction_id' => 'mt-hist-low',
            'drop_off_time_slot_id' => $fixtures['slotA']->id,
            'pick_up_time_slot_id' => $fixtures['slotB']->id,
            'reservation_date' => '2026-06-01',
            'user_name' => 'Old Guest',
            'country' => 'ME',
            'license_plate' => DuplicateReservationAttemptService::normalizeLicensePlate($plate),
            'vehicle_type_id' => $fixtures['low']->id,
            'email' => 'old@example.com',
            'preferred_locale' => 'cg',
            'status' => 'paid',
            'invoice_amount' => 15.00,
        ]);

        $temp = $this->createGuestTempData($fixtures, $plate, (int) $fixtures['high']->id, 'mt-guest-higher');

        $this->assertTrue(app(PaymentSuccessHandler::class)->handle($temp, ['source' => 'test'], true, true));

        $this->assertSame(0, AdminAlert::query()->where('type', 'guest_paid_lower_category_than_history')->count());
        Mail::assertNothingSent();
    }

    public function test_agency_paid_reservation_does_not_trigger_guest_alert(): void
    {
        Mail::fake();
        $fixtures = $this->seedFixtures();
        $user = User::factory()->create();
        $plate = 'KO111AA';

        Reservation::query()->create([
            'user_id' => $user->id,
            'merchant_transaction_id' => 'mt-agency-hist',
            'drop_off_time_slot_id' => $fixtures['slotA']->id,
            'pick_up_time_slot_id' => $fixtures['slotB']->id,
            'reservation_date' => '2026-06-01',
            'user_name' => $user->name,
            'country' => 'ME',
            'license_plate' => DuplicateReservationAttemptService::normalizeLicensePlate($plate),
            'vehicle_type_id' => $fixtures['high']->id,
            'email' => $user->email,
            'preferred_locale' => 'cg',
            'status' => 'paid',
            'invoice_amount' => 40.00,
        ]);

        $temp = TempData::query()->create([
            'merchant_transaction_id' => 'mt-agency-new',
            'retry_token' => 'retry-agency',
            'user_id' => $user->id,
            'reservation_kind' => ReservationKind::TIME_SLOTS,
            'drop_off_time_slot_id' => $fixtures['slotA']->id,
            'pick_up_time_slot_id' => $fixtures['slotB']->id,
            'reservation_date' => '2026-07-01',
            'user_name' => $user->name,
            'country' => 'ME',
            'license_plate' => $plate,
            'vehicle_type_id' => $fixtures['low']->id,
            'email' => $user->email,
            'preferred_locale' => 'cg',
            'status' => TempData::STATUS_PENDING,
            'invoice_amount_snapshot' => 15.00,
        ]);

        $this->assertTrue(app(PaymentSuccessHandler::class)->handle($temp, ['source' => 'test'], true, true));

        $this->assertSame(0, AdminAlert::query()->where('type', 'guest_paid_lower_category_than_history')->count());
        Mail::assertNothingSent();
    }

    public function test_historical_free_reservation_is_ignored(): void
    {
        Mail::fake();
        $fixtures = $this->seedFixtures();
        $plate = 'PGFREE1';

        Reservation::query()->create([
            'user_id' => null,
            'merchant_transaction_id' => 'mt-free-hist',
            'drop_off_time_slot_id' => $fixtures['slotA']->id,
            'pick_up_time_slot_id' => $fixtures['slotB']->id,
            'reservation_date' => '2026-06-01',
            'user_name' => 'Free Guest',
            'country' => 'ME',
            'license_plate' => DuplicateReservationAttemptService::normalizeLicensePlate($plate),
            'vehicle_type_id' => $fixtures['high']->id,
            'email' => 'free@example.com',
            'preferred_locale' => 'cg',
            'status' => 'free',
            'invoice_amount' => 0,
        ]);

        $temp = $this->createGuestTempData($fixtures, $plate, (int) $fixtures['low']->id, 'mt-after-free');

        $this->assertTrue(app(PaymentSuccessHandler::class)->handle($temp, ['source' => 'test'], true, true));

        $this->assertSame(0, AdminAlert::query()->where('type', 'guest_paid_lower_category_than_history')->count());
    }

    public function test_uses_most_recent_historical_paid_reservation_for_comparison(): void
    {
        Mail::fake();
        $fixtures = $this->seedFixtures();
        $plate = 'PGHIST2';

        Reservation::query()->create([
            'user_id' => null,
            'merchant_transaction_id' => 'mt-old-high',
            'drop_off_time_slot_id' => $fixtures['slotA']->id,
            'pick_up_time_slot_id' => $fixtures['slotB']->id,
            'reservation_date' => '2026-05-01',
            'user_name' => 'Older',
            'country' => 'ME',
            'license_plate' => DuplicateReservationAttemptService::normalizeLicensePlate($plate),
            'vehicle_type_id' => $fixtures['high']->id,
            'email' => 'older@example.com',
            'preferred_locale' => 'cg',
            'status' => 'paid',
            'invoice_amount' => 40.00,
            'created_at' => '2026-05-01 10:00:00',
        ]);

        Reservation::query()->create([
            'user_id' => null,
            'merchant_transaction_id' => 'mt-recent-low',
            'drop_off_time_slot_id' => $fixtures['slotA']->id,
            'pick_up_time_slot_id' => $fixtures['slotB']->id,
            'reservation_date' => '2026-06-01',
            'user_name' => 'Recent',
            'country' => 'ME',
            'license_plate' => DuplicateReservationAttemptService::normalizeLicensePlate($plate),
            'vehicle_type_id' => $fixtures['low']->id,
            'email' => 'recent@example.com',
            'preferred_locale' => 'cg',
            'status' => 'paid',
            'invoice_amount' => 15.00,
            'created_at' => '2026-06-01 10:00:00',
        ]);

        $temp = $this->createGuestTempData($fixtures, $plate, (int) $fixtures['low']->id, 'mt-same-as-recent');

        $this->assertTrue(app(PaymentSuccessHandler::class)->handle($temp, ['source' => 'test'], true, true));

        $this->assertSame(0, AdminAlert::query()->where('type', 'guest_paid_lower_category_than_history')->count());
    }
}
