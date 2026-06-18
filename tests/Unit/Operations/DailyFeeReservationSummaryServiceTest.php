<?php

namespace Tests\Unit\Operations;

use App\Models\Reservation;
use App\Models\VehicleType;
use App\Services\Operations\DailyFeeReservationSummaryService;
use App\Support\ReservationKind;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DailyFeeReservationSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_counts_paid_daily_ticket_reservations_for_the_day(): void
    {
        $vt = VehicleType::query()->create(['price' => 15]);
        $day = Carbon::parse('2026-04-26')->startOfDay();
        $date = $day->toDateString();

        $base = [
            'drop_off_time_slot_id' => null,
            'pick_up_time_slot_id' => null,
            'reservation_date' => $date,
            'user_name' => 'Test',
            'country' => 'ME',
            'license_plate' => 'KO1',
            'vehicle_type_id' => $vt->id,
            'email' => 'test@example.com',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
            'reservation_kind' => ReservationKind::DAILY_TICKET,
        ];

        Reservation::query()->create(array_merge($base, [
            'merchant_transaction_id' => 'mt-daily-1',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'license_plate' => 'KO1',
        ]));
        Reservation::query()->create(array_merge($base, [
            'merchant_transaction_id' => 'mt-daily-2',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'license_plate' => 'KO2',
        ]));
        Reservation::query()->create(array_merge($base, [
            'merchant_transaction_id' => 'mt-daily-other-day',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'license_plate' => 'KO3',
            'reservation_date' => '2026-04-27',
        ]));
        Reservation::query()->create(array_merge($base, [
            'merchant_transaction_id' => 'mt-slots-1',
            'status' => 'paid',
            'invoice_amount' => '15.00',
            'license_plate' => 'KO4',
            'reservation_kind' => ReservationKind::TIME_SLOTS,
        ]));

        $data = (new DailyFeeReservationSummaryService())->forDate($day);

        $this->assertSame($date, $data['date']);
        $this->assertSame(2, $data['total']);
    }
}
