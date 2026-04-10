<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use App\Models\User;
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

    public function test_web_user_is_not_treated_as_panel_admin_on_admin_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('panel_admin.login', [], false));

        $this->actingAs($user)
            ->get('/admin/login')
            ->assertOk();
    }

    public function test_panel_admin_logged_in_is_redirected_away_from_login_form(): void
    {
        $admin = Admin::query()->create([
            'username' => 'panellogged',
            'email' => 'panel-logged@example.com',
            'password' => bcrypt('secret-password-3'),
            'control_access' => false,
            'admin_access' => true,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get('/admin/login')
            ->assertRedirect(route('panel_admin.dashboard', [], false));
    }

    public function test_admin_without_panel_flags_gets_403_on_dashboard(): void
    {
        $admin = Admin::query()->create([
            'username' => 'nopanel',
            'email' => 'no-panel@example.com',
            'password' => bcrypt('secret-password-4'),
            'control_access' => false,
            'admin_access' => false,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_control_only_admin_session_gets_403_on_panel(): void
    {
        $admin = Admin::query()->create([
            'username' => 'controlonly403',
            'email' => 'control-only-403@example.com',
            'password' => bcrypt('secret-password-4b'),
            'control_access' => true,
            'admin_access' => false,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_logout_then_admin_dashboard_requires_login_again(): void
    {
        $admin = Admin::query()->create([
            'username' => 'panellogout',
            'email' => 'panel-logout@example.com',
            'password' => bcrypt('secret-password-5'),
            'control_access' => false,
            'admin_access' => true,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->post('/admin/logout')
            ->assertRedirect(route('panel_admin.login', [], false));

        $this->get('/admin')->assertRedirect(route('panel_admin.login', [], false));
    }
}
