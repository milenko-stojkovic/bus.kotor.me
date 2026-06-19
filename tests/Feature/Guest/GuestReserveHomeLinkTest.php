<?php

namespace Tests\Feature\Guest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GuestReserveHomeLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_reserve_shows_home_link_to_landing(): void
    {
        $this->get(route('guest.reserve', [], false))
            ->assertOk()
            ->assertSee('href="'.route('landing', [], false).'"', false)
            ->assertSee('M3 12l2-2m0 0l7-7 7 7M5 10v10', false);
    }

    public function test_landing_does_not_show_home_link(): void
    {
        $this->get(route('landing', [], false))
            ->assertOk()
            ->assertDontSee('M3 12l2-2m0 0l7-7 7 7M5 10v10', false);
    }
}
