<?php

namespace Tests\Unit;

use App\Models\ListOfTimeSlot;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ListOfTimeSlotArrivalWindowTest extends TestCase
{
    public function test_end_time_24_00_is_midnight_next_day(): void
    {
        $slot = new ListOfTimeSlot(['time_slot' => '20:00 - 24:00']);
        $day = Carbon::parse('2026-08-10', 'Europe/Podgorica')->startOfDay();
        $end = $slot->getEndTimeForDate($day);

        $this->assertNotNull($end);
        $this->assertSame('2026-08-11 00:00:00', $end->format('Y-m-d H:i:s'));
        $this->assertSame('Europe/Podgorica', $end->timezone->getName());
    }

    public function test_slot_20_00_to_24_00_visible_during_evening(): void
    {
        $slot = new ListOfTimeSlot(['time_slot' => '20:00 - 24:00']);
        $day = Carbon::parse('2026-08-10', 'Europe/Podgorica')->startOfDay();
        $now = Carbon::parse('2026-08-10 20:33:00', 'Europe/Podgorica');

        $this->assertTrue($slot->isInArrivalControlWindow($now, $day, 3));
    }

    public function test_slot_20_00_to_24_00_hidden_before_preview_window(): void
    {
        $slot = new ListOfTimeSlot(['time_slot' => '20:00 - 24:00']);
        $day = Carbon::parse('2026-08-10', 'Europe/Podgorica')->startOfDay();
        $now = Carbon::parse('2026-08-10 16:59:00', 'Europe/Podgorica');

        $this->assertFalse($slot->isInArrivalControlWindow($now, $day, 3));
    }

    public function test_tomorrow_early_slot_visible_from_21_00_previous_day(): void
    {
        $slot = new ListOfTimeSlot(['time_slot' => '00:00 - 07:00']);
        $reservationDay = Carbon::parse('2026-08-11', 'Europe/Podgorica')->startOfDay();
        $now = Carbon::parse('2026-08-10 21:00:00', 'Europe/Podgorica');

        $this->assertTrue($slot->isInArrivalControlWindow($now, $reservationDay, 3));
    }

    public function test_tomorrow_early_slot_hidden_at_20_59_previous_day(): void
    {
        $slot = new ListOfTimeSlot(['time_slot' => '00:00 - 07:00']);
        $reservationDay = Carbon::parse('2026-08-11', 'Europe/Podgorica')->startOfDay();
        $now = Carbon::parse('2026-08-10 20:59:00', 'Europe/Podgorica');

        $this->assertFalse($slot->isInArrivalControlWindow($now, $reservationDay, 3));
    }

    public function test_tomorrow_early_slot_visible_until_end_morning(): void
    {
        $slot = new ListOfTimeSlot(['time_slot' => '00:00 - 07:00']);
        $reservationDay = Carbon::parse('2026-08-11', 'Europe/Podgorica')->startOfDay();
        $now = Carbon::parse('2026-08-11 06:30:00', 'Europe/Podgorica');

        $this->assertTrue($slot->isInArrivalControlWindow($now, $reservationDay, 3));
    }

    public function test_tomorrow_early_slot_hidden_after_end(): void
    {
        $slot = new ListOfTimeSlot(['time_slot' => '00:00 - 07:00']);
        $reservationDay = Carbon::parse('2026-08-11', 'Europe/Podgorica')->startOfDay();
        $now = Carbon::parse('2026-08-11 07:00:00', 'Europe/Podgorica');

        $this->assertFalse($slot->isInArrivalControlWindow($now, $reservationDay, 3));
    }
}
