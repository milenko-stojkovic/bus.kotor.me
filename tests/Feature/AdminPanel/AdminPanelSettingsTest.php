<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use App\Models\ReportEmail;
use App\Models\SystemConfig;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function seedAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'settingsadmin',
            'email' => 'settings-admin@example.com',
            'password' => bcrypt('secret-password-st'),
            'control_access' => false,
            'admin_access' => true,
        ]);
    }

    public function test_settings_page_renders_for_panel_admin(): void
    {
        $admin = $this->seedAdmin();
        SystemConfig::setValue('available_parking_slots', 9);
        ReportEmail::query()->create(['email' => 'b@example.com']);
        ReportEmail::query()->create(['email' => 'a@example.com']);

        $this->actingAs($admin, 'panel_admin');

        $this->get(route('panel_admin.settings', [], false))
            ->assertOk()
            ->assertSee('Podešavanja', false)
            ->assertSee('Kapacitet', false)
            ->assertSee('Izvještaji - email adrese', false)
            ->assertSee('a@example.com', false)
            ->assertSee('b@example.com', false);
    }

    public function test_capacity_update_validates_range_and_saves_value(): void
    {
        $admin = $this->seedAdmin();
        SystemConfig::setValue('available_parking_slots', 9);

        $this->actingAs($admin, 'panel_admin');

        $resp = $this->put(route('panel_admin.settings.capacity.update', [], false), [
            'available_parking_slots' => 12,
        ]);

        $resp->assertRedirect(route('panel_admin.settings', [], false));
        $this->assertSame(12, (int) SystemConfig::getValue('available_parking_slots'));

        $effective = Carbon::now()->startOfDay()->addDays(91)->format('d.m.Y.');
        $resp->assertSessionHas('status');
        $this->assertStringContainsString($effective, (string) session('status'));
    }

    public function test_capacity_update_rejects_invalid_value(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $this->from(route('panel_admin.settings', [], false))
            ->put(route('panel_admin.settings.capacity.update', [], false), [
                'available_parking_slots' => 0,
            ])
            ->assertRedirect(route('panel_admin.settings', [], false))
            ->assertSessionHasErrors('available_parking_slots');
    }

    public function test_report_email_store_trims_lowercases_and_prevents_duplicates(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $this->post(route('panel_admin.settings.report-emails.store', [], false), [
            'email' => '  TEST@Example.COM ',
        ])->assertRedirect(route('panel_admin.settings', [], false));

        $this->assertTrue(ReportEmail::query()->where('email', 'test@example.com')->exists());

        $this->post(route('panel_admin.settings.report-emails.store', [], false), [
            'email' => 'test@example.com',
        ])->assertSessionHasErrors('email');
    }

    public function test_report_email_destroy_hard_deletes(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $re = ReportEmail::query()->create(['email' => 'x@example.com']);

        $this->delete(route('panel_admin.settings.report-emails.destroy', $re, false))
            ->assertRedirect(route('panel_admin.settings', [], false));

        $this->assertFalse(ReportEmail::query()->whereKey($re->id)->exists());
    }
}

