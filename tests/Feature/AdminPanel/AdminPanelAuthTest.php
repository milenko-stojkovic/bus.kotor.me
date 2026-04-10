<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_visiting_admin_dashboard(): void
    {
        $this->get('/admin')->assertRedirect(route('panel_admin.login', [], false));
    }

    public function test_admin_with_admin_access_can_login_and_see_warnings_page(): void
    {
        Admin::query()->create([
            'username' => 'paneltest',
            'email' => 'panel-test@example.com',
            'password' => bcrypt('secret-password-1'),
            'control_access' => false,
            'admin_access' => true,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'panel-test@example.com',
            'password' => 'secret-password-1',
        ]);

        $response->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Upozorenja', false);
    }

    public function test_control_only_user_cannot_login_to_admin_panel(): void
    {
        Admin::query()->create([
            'username' => 'onlycontrol',
            'email' => 'only-control@example.com',
            'password' => bcrypt('secret-password-2'),
            'control_access' => true,
            'admin_access' => false,
        ]);

        $this->post('/admin/login', [
            'email' => 'only-control@example.com',
            'password' => 'secret-password-2',
        ])->assertSessionHasErrors('email');
    }
}
