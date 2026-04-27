<?php

namespace Tests\Feature\Panel;

use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FzbrSlotsRequiredCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fzbr_slots_endpoint_marks_slots_disabled_when_required_exceeds_available_capacity(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $d = Carbon::now()->addDays(3)->toDateString();

        $slot = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        DailyParkingData::query()->create([
            'date' => $d,
            'time_slot_id' => $slot->id,
            'capacity' => 3,
            'reserved' => 0,
            'pending' => 0,
            'is_blocked' => false,
        ]);

        $json = $this->getJson(route('panel.fzbr.slots', [
            'reservation_date' => $d,
            'required' => 4,
        ], false))->assertOk()->json();

        $rows = collect($json['arrival_slots'] ?? [])->keyBy('id');
        $this->assertTrue((bool) ($rows[(int) $slot->id]['disabled'] ?? true));
    }
}
