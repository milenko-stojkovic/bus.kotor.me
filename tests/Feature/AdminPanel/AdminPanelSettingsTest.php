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
        ReportEmail::query()->create(['email' => 'b@example.com', 'purpose' => ReportEmail::PURPOSE_REPORT]);
        ReportEmail::query()->create(['email' => 'a@example.com', 'purpose' => ReportEmail::PURPOSE_REPORT]);
        ReportEmail::query()->create(['email' => 'limo-only@example.com', 'purpose' => ReportEmail::PURPOSE_LIMO_INCIDENTS]);

        $this->actingAs($admin, 'panel_admin');

        $html = $this->get(route('panel_admin.settings', [], false))
            ->assertOk()
            ->assertSee('Podešavanja', false)
            ->assertSee('Kapacitet', false)
            ->assertSee('Izvještaji - email adrese', false)
            ->assertSee('Incident - email adrese', false)
            ->assertSee('a@example.com', false)
            ->assertSee('b@example.com', false)
            ->assertSee('limo-only@example.com', false)
            ->getContent();

        $posIzv = strpos((string) $html, 'Izvještaji - email adrese');
        $posInc = strpos((string) $html, 'Incident - email adrese');
        $this->assertNotFalse($posIzv);
        $this->assertNotFalse($posInc);
        $this->assertGreaterThan($posIzv, $posInc);
        $segment = substr((string) $html, $posIzv, $posInc - $posIzv);
        $this->assertStringContainsString('a@example.com', $segment);
        $this->assertStringNotContainsString('limo-only@example.com', $segment);
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

        $this->assertTrue(ReportEmail::query()->forReport()->where('email', 'test@example.com')->exists());

        $this->post(route('panel_admin.settings.report-emails.store', [], false), [
            'email' => 'test@example.com',
        ])->assertSessionHasErrors('email');
    }

    public function test_limo_incident_email_store_trims_lowercases_and_prevents_duplicates(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $this->post(route('panel_admin.settings.limo-incident-emails.store', [], false), [
            'limo_incident_email' => '  LIMO@Example.COM ',
        ])->assertRedirect(route('panel_admin.settings', [], false));

        $this->assertTrue(ReportEmail::query()->forLimoIncidents()->where('email', 'limo@example.com')->exists());

        $this->post(route('panel_admin.settings.limo-incident-emails.store', [], false), [
            'limo_incident_email' => 'limo@example.com',
        ])->assertSessionHasErrors('limo_incident_email');
    }

    public function test_same_email_may_exist_for_reports_and_limo_incidents_separately(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $this->post(route('panel_admin.settings.report-emails.store', [], false), [
            'email' => 'shared@example.com',
        ])->assertRedirect(route('panel_admin.settings', [], false));

        $this->post(route('panel_admin.settings.limo-incident-emails.store', [], false), [
            'limo_incident_email' => 'shared@example.com',
        ])->assertRedirect(route('panel_admin.settings', [], false));

        $this->assertSame(2, ReportEmail::query()->where('email', 'shared@example.com')->count());
    }

    public function test_limo_incident_email_destroy_hard_deletes(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $re = ReportEmail::query()->create([
            'email' => 'limo-del@example.com',
            'purpose' => ReportEmail::PURPOSE_LIMO_INCIDENTS,
        ]);

        $this->delete(route('panel_admin.settings.limo-incident-emails.destroy', $re, false))
            ->assertRedirect(route('panel_admin.settings', [], false));

        $this->assertFalse(ReportEmail::query()->whereKey($re->id)->exists());
    }

    public function test_destroy_report_email_route_rejects_limo_incident_row(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $re = ReportEmail::query()->create([
            'email' => 'x@example.com',
            'purpose' => ReportEmail::PURPOSE_LIMO_INCIDENTS,
        ]);

        $this->delete(route('panel_admin.settings.report-emails.destroy', $re, false))
            ->assertNotFound();
    }

    public function test_destroy_limo_incident_email_route_rejects_reports_row(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $re = ReportEmail::query()->create([
            'email' => 'y@example.com',
            'purpose' => ReportEmail::PURPOSE_REPORT,
        ]);

        $this->delete(route('panel_admin.settings.limo-incident-emails.destroy', $re, false))
            ->assertNotFound();
    }

    public function test_report_email_destroy_hard_deletes(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $re = ReportEmail::query()->create(['email' => 'x@example.com', 'purpose' => ReportEmail::PURPOSE_REPORT]);

        $this->delete(route('panel_admin.settings.report-emails.destroy', $re, false))
            ->assertRedirect(route('panel_admin.settings', [], false));

        $this->assertFalse(ReportEmail::query()->whereKey($re->id)->exists());
    }

    public function test_incident_settings_empty_page_does_not_render_komunalna_fallback_address(): void
    {
        $admin = $this->seedAdmin();
        SystemConfig::setValue('available_parking_slots', 9);
        $this->actingAs($admin, 'panel_admin');

        $html = (string) $this->get(route('panel_admin.settings', [], false))->assertOk()->getContent();

        $this->assertStringNotContainsString('komunalna.policija@kotor.me', $html);
    }

    public function test_incident_add_button_and_form_markers_are_present(): void
    {
        $admin = $this->seedAdmin();
        SystemConfig::setValue('available_parking_slots', 9);
        $this->actingAs($admin, 'panel_admin');

        $html = (string) $this->get(route('panel_admin.settings', [], false))->assertOk()->getContent();

        $this->assertStringContainsString('id="settings-incident-email-add"', $html);
        $this->assertStringContainsString('id="settings-incident-email-form"', $html);
        $this->assertStringContainsString('name="limo_incident_email"', $html);
        $this->assertStringContainsString(route('panel_admin.settings.limo-incident-emails.store', [], false), $html);
    }

    public function test_admin_can_add_incident_email_and_it_appears_only_in_incident_section(): void
    {
        $admin = $this->seedAdmin();
        SystemConfig::setValue('available_parking_slots', 9);
        $this->actingAs($admin, 'panel_admin');

        $this->post(route('panel_admin.settings.limo-incident-emails.store', [], false), [
            'limo_incident_email' => 'incident-ui@example.com',
        ])->assertRedirect(route('panel_admin.settings', [], false));

        $this->assertTrue(ReportEmail::query()->forLimoIncidents()->where('email', 'incident-ui@example.com')->exists());

        $html = (string) $this->get(route('panel_admin.settings', [], false))->assertOk()->getContent();

        $posIzv = strpos($html, 'Izvještaji - email adrese');
        $posInc = strpos($html, 'Incident - email adrese');
        $this->assertNotFalse($posIzv);
        $this->assertNotFalse($posInc);
        $this->assertGreaterThan($posIzv, $posInc);

        $reportSegment = substr($html, $posIzv, $posInc - $posIzv);
        $this->assertStringNotContainsString('incident-ui@example.com', $reportSegment);

        $this->assertStringContainsString('incident-ui@example.com', $html);
    }

    public function test_report_email_does_not_appear_in_incident_section(): void
    {
        $admin = $this->seedAdmin();
        SystemConfig::setValue('available_parking_slots', 9);
        ReportEmail::query()->create(['email' => 'only-reports@example.com', 'purpose' => ReportEmail::PURPOSE_REPORT]);

        $this->actingAs($admin, 'panel_admin');

        $html = (string) $this->get(route('panel_admin.settings', [], false))->assertOk()->getContent();

        $posInc = strpos($html, 'Incident - email adrese');
        $this->assertNotFalse($posInc);
        $incidentTail = substr($html, $posInc);
        $this->assertStringNotContainsString('only-reports@example.com', $incidentTail);
    }

    public function test_incident_email_does_not_appear_in_reports_section(): void
    {
        $admin = $this->seedAdmin();
        SystemConfig::setValue('available_parking_slots', 9);
        ReportEmail::query()->create(['email' => 'only-limo@example.com', 'purpose' => ReportEmail::PURPOSE_LIMO_INCIDENTS]);

        $this->actingAs($admin, 'panel_admin');

        $html = (string) $this->get(route('panel_admin.settings', [], false))->assertOk()->getContent();

        $posIzv = strpos($html, 'Izvještaji - email adrese');
        $posInc = strpos($html, 'Incident - email adrese');
        $this->assertNotFalse($posIzv);
        $this->assertNotFalse($posInc);
        $reportSegment = substr($html, $posIzv, $posInc - $posIzv);
        $this->assertStringNotContainsString('only-limo@example.com', $reportSegment);
    }
}

