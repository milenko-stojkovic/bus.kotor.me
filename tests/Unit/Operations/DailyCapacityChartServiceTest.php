<?php

namespace Tests\Unit\Operations;

use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\SystemConfig;
use App\Services\Operations\DailyCapacityChartService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DailyCapacityChartServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_returns_slots_1_to_41_and_preserves_over_capacity_totals(): void
    {
        for ($i = 1; $i <= 41; $i++) {
            ListOfTimeSlot::query()->create(['time_slot' => 'T'.$i]);
        }

        // Capacity fallback: if missing or 0 -> 9
        SystemConfig::setValue('available_parking_slots', 0);

        $svc = new DailyCapacityChartService();
        $day = Carbon::parse('2026-04-26')->startOfDay();

        $slot1 = ListOfTimeSlot::query()->orderBy('id')->firstOrFail();
        DailyParkingData::query()->create([
            'date' => $day->toDateString(),
            'time_slot_id' => $slot1->id,
            'capacity' => 9,
            'reserved' => 10,
            'pending' => 2,
            'is_blocked' => false,
        ]);

        $data = $svc->forDate($day);

        $this->assertSame('2026-04-26', $data['date']);
        $this->assertSame(9, (int) $data['capacity']);
        $this->assertCount(41, $data['slots']);
        $this->assertSame(1, $data['slots'][0]['slot_number']);
        $this->assertSame(41, $data['slots'][40]['slot_number']);

        $this->assertSame(10, $data['slots'][0]['reserved']);
        $this->assertSame(2, $data['slots'][0]['pending']);
        $this->assertSame(12, $data['slots'][0]['total']);
    }
}

