<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use App\Models\AdminAlert;
use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWarningsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_warnings_dashboard_does_not_show_guest_placeholder_or_past_slot_copy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', config('app.timezone')));

        $admin = Admin::query()->create([
            'username' => 'warnuser',
            'email' => 'warn-dash@example.com',
            'password' => bcrypt('secret-password-w'),
            'control_access' => false,
            'admin_access' => true,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('nije implementirano', false)
            ->assertDontSee('Prošli termini', false)
            ->assertDontSee('plaćeni prozor', false)
            ->assertDontSee('plaćeni termin', false)
            ->assertSee('Nedostupni dani i termini', false)
            ->assertSee('Blokirani dani i termini', false);
    }

    public function test_warnings_dashboard_shows_status_text_but_no_transition_buttons(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', config('app.timezone')));

        $admin = $this->makeAdmin();
        AdminAlert::query()->create([
            'type' => 'free_reservation_request',
            'status' => AdminAlert::STATUS_UNREAD,
            'title' => 'Zahtjev',
            'message' => 'Stigao je zahtjev.',
            'payload_json' => ['free_reservation_request_id' => 1],
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Status:', false)
            ->assertDontSee('U obradi', false)
            ->assertDontSee('Završen', false)
            ->assertDontSee('Ukloni', false);
    }

    public function test_blocked_consecutive_slots_merge_to_single_span(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', config('app.timezone')));

        $admin = $this->makeAdmin();
        $s1 = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $s2 = ListOfTimeSlot::query()->create(['time_slot' => '10:20 - 10:40']);
        $s3 = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $date = '2026-04-15';

        foreach ([$s1, $s2] as $slot) {
            DailyParkingData::query()->create([
                'date' => $date,
                'time_slot_id' => $slot->id,
                'capacity' => 5,
                'reserved' => 0,
                'pending' => 0,
                'is_blocked' => true,
            ]);
        }
        DailyParkingData::query()->create([
            'date' => $date,
            'time_slot_id' => $s3->id,
            'capacity' => 5,
            'reserved' => 0,
            'pending' => 0,
            'is_blocked' => false,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('10:00 - 10:40', false);
    }

    public function test_blocked_full_day_shows_blokiran_without_ranges(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', config('app.timezone')));

        $admin = $this->makeAdmin();
        $s1 = ListOfTimeSlot::query()->create(['time_slot' => '08:00 - 08:20']);
        $s2 = ListOfTimeSlot::query()->create(['time_slot' => '09:00 - 09:20']);
        $date = '2026-04-16';

        foreach ([$s1, $s2] as $slot) {
            DailyParkingData::query()->create([
                'date' => $date,
                'time_slot_id' => $slot->id,
                'capacity' => 5,
                'reserved' => 0,
                'pending' => 0,
                'is_blocked' => true,
            ]);
        }

        $this->actingAs($admin, 'panel_admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee(Carbon::parse($date)->format('d.m.Y.'), false)
            ->assertSee('— blokiran', false);
    }

    public function test_unavailable_partial_day_when_only_one_slot_has_no_capacity(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', config('app.timezone')));

        $admin = $this->makeAdmin();
        $busy = ListOfTimeSlot::query()->create(['time_slot' => '14:00 - 14:20']);
        $free = ListOfTimeSlot::query()->create(['time_slot' => '15:00 - 15:20']);
        $date = '2026-04-17';

        DailyParkingData::query()->create([
            'date' => $date,
            'time_slot_id' => $busy->id,
            'capacity' => 2,
            'reserved' => 2,
            'pending' => 0,
            'is_blocked' => false,
        ]);
        DailyParkingData::query()->create([
            'date' => $date,
            'time_slot_id' => $free->id,
            'capacity' => 5,
            'reserved' => 0,
            'pending' => 0,
            'is_blocked' => false,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('14:00 - 14:20', false)
            ->assertDontSee('— nedostupan', false);
    }

    public function test_unavailable_full_day_when_every_slot_unpurchaseable(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', config('app.timezone')));

        $admin = $this->makeAdmin();
        $s1 = ListOfTimeSlot::query()->create(['time_slot' => '16:00 - 16:20']);
        $s2 = ListOfTimeSlot::query()->create(['time_slot' => '17:00 - 17:20']);
        $date = '2026-04-18';

        foreach ([$s1, $s2] as $slot) {
            DailyParkingData::query()->create([
                'date' => $date,
                'time_slot_id' => $slot->id,
                'capacity' => 1,
                'reserved' => 1,
                'pending' => 0,
                'is_blocked' => false,
            ]);
        }

        $this->actingAs($admin, 'panel_admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('— nedostupan', false);
    }

    public function test_blocked_slot_appears_in_blocked_section_and_still_in_unavailable(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', config('app.timezone')));

        $admin = $this->makeAdmin();
        $blockedSlot = ListOfTimeSlot::query()->create(['time_slot' => '18:00 - 18:20']);
        $openSlot = ListOfTimeSlot::query()->create(['time_slot' => '19:00 - 19:20']);
        $date = '2026-04-19';

        DailyParkingData::query()->create([
            'date' => $date,
            'time_slot_id' => $blockedSlot->id,
            'capacity' => 5,
            'reserved' => 0,
            'pending' => 0,
            'is_blocked' => true,
        ]);
        DailyParkingData::query()->create([
            'date' => $date,
            'time_slot_id' => $openSlot->id,
            'capacity' => 5,
            'reserved' => 0,
            'pending' => 0,
            'is_blocked' => false,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('18:00 - 18:20', false)
            ->assertSee('Deblokiraj', false);
    }

    public function test_empty_daily_data_shows_neutral_empty_messages(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', config('app.timezone')));

        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'panel_admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Nema nedostupnih termina za prikazane datume.', false)
            ->assertSee('Nema blokiranih termina.', false);
    }

    private function makeAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'warn'.uniqid(),
            'email' => 'warn-'.uniqid().'@example.com',
            'password' => bcrypt('secret-password-w'),
            'control_access' => false,
            'admin_access' => true,
        ]);
    }
}
