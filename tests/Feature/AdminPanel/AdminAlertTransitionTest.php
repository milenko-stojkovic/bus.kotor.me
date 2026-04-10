<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use App\Models\AdminAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAlertTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_to_in_progress_to_done_to_removed(): void
    {
        $admin = Admin::query()->create([
            'username' => 'panelalert',
            'email' => 'panel-alert@example.com',
            'password' => bcrypt('secret-password-3'),
            'control_access' => false,
            'admin_access' => true,
        ]);

        $alert = AdminAlert::query()->create([
            'type' => 'test',
            'status' => AdminAlert::STATUS_UNREAD,
            'title' => 'T',
            'message' => 'M',
            'payload_json' => ['foo' => 'bar'],
        ]);

        $this->actingAs($admin, 'panel_admin');

        $this->post(route('panel_admin.alerts.transition', $alert, false), [
            'action' => 'in_progress',
        ])->assertRedirect();
        $this->assertSame(AdminAlert::STATUS_IN_PROGRESS, $alert->fresh()->status);

        $this->post(route('panel_admin.alerts.transition', $alert, false), [
            'action' => 'done',
        ])->assertRedirect();
        $this->assertSame(AdminAlert::STATUS_DONE, $alert->fresh()->status);
        $this->assertNotNull($alert->fresh()->resolved_at);

        $this->post(route('panel_admin.alerts.transition', $alert, false), [
            'action' => 'remove',
        ])->assertRedirect();
        $this->assertNotNull($alert->fresh()->removed_at);
    }
}
