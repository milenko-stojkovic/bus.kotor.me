<?php

namespace Tests\Unit;

use App\Models\ListOfTimeSlot;
use App\Services\AdminPanel\Blocking\DaySlotRangeSummaryBuilder;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class DaySlotRangeSummaryBuilderTest extends TestCase
{
    public function test_merges_consecutive_slot_ids_into_start_end_span(): void
    {
        $a = new ListOfTimeSlot(['time_slot' => '10:00 - 10:20']);
        $a->id = 1;
        $b = new ListOfTimeSlot(['time_slot' => '10:20 - 10:40']);
        $b->id = 2;
        $c = new ListOfTimeSlot(['time_slot' => '11:00 - 11:20']);
        $c->id = 3;
        $slots = new Collection([$a, $b, $c]);

        $builder = new DaySlotRangeSummaryBuilder;
        $out = $builder->summarize($slots, [2, 1]);

        $this->assertFalse($out['is_full_day']);
        $this->assertSame(['10:00 - 10:40'], $out['ranges']);
    }

    public function test_full_catalog_selected_marks_full_day(): void
    {
        $a = new ListOfTimeSlot(['time_slot' => '08:00 - 08:20']);
        $a->id = 1;
        $b = new ListOfTimeSlot(['time_slot' => '09:00 - 09:20']);
        $b->id = 2;
        $slots = new Collection([$a, $b]);

        $builder = new DaySlotRangeSummaryBuilder;
        $out = $builder->summarize($slots, [1, 2]);

        $this->assertTrue($out['is_full_day']);
        $this->assertSame([], $out['ranges']);
    }
}
