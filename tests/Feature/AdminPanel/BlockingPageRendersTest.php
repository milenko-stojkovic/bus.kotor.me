<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockingPageRendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocking_page_renders_for_admin_panel_user(): void
    {
        $admin = Admin::query()->create([
            'username' => 'blocktest',
            'email' => 'block-test@example.com',
            'password' => bcrypt('secret-password-4'),
            'control_access' => false,
            'admin_access' => true,
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->get('/admin/blokiranje')
            ->assertOk()
            ->assertSee('Blokiranje', false)
            ->assertSee('Blokirani dani i termini', false);
    }
}

