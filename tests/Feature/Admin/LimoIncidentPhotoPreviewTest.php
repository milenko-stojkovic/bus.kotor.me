<?php

namespace Tests\Feature\Admin;

use App\Contracts\MegaArchiveClient;
use App\Models\Admin;
use App\Models\ExternalFileArchive;
use App\Models\LimoIncident;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MegaArchiveFakeClient;
use Tests\TestCase;

final class LimoIncidentPhotoPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-15 12:00:00', 'Europe/Podgorica'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_preview_local_incident_plate_photo(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        [$admin, $incident, $platePath] = $this->seedIncidentWithPaths(includeBranding: true);
        Storage::disk('local')->put($platePath, random_bytes(200));
        Storage::disk('local')->put((string) $incident->branding_photo_path, random_bytes(100));

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.incidents.plate-photo-preview', $incident, false))
            ->assertOk();

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.incidents.branding-photo-preview', $incident, false))
            ->assertOk();

        $this->assertSame(0, $fake->downloadCalls);
    }

    public function test_incident_plate_preview_restores_from_mega_when_archived_and_local_missing(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        [$admin, $incident, $platePath] = $this->seedIncidentWithPaths(includeBranding: false);

        ExternalFileArchive::query()->create([
            'source_table' => (new LimoIncident)->getTable(),
            'source_id' => $incident->id,
            'source_column' => 'plate_photo_path',
            'context_type' => 'limo_incident_plate',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'inc__plate_'.$incident->id.'__'.Str::uuid()->toString().'.jpg',
            'mega_node_id' => 'n',
            'mega_path' => 'bus.kotor/inc.jpg',
            'original_local_path' => $platePath,
            'local_deleted_at' => now()->subDay(),
            'archived_at' => now()->subDay(),
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'error_message' => null,
        ]);

        $this->assertFalse(Storage::disk('local')->exists($platePath));

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.incidents.plate-photo-preview', $incident, false))
            ->assertOk();

        $this->assertSame(1, $fake->downloadCalls);
        $this->assertTrue(Storage::disk('local')->exists($platePath));
    }

    public function test_non_admin_cannot_access_incident_plate_preview(): void
    {
        Storage::fake('local');
        [$admin, $incident, $platePath] = $this->seedIncidentWithPaths(includeBranding: false);
        Storage::disk('local')->put($platePath, 'x');

        $this->get(route('admin.limo.incidents.plate-photo-preview', $incident, false))
            ->assertRedirect();
    }

    public function test_unsafe_incident_plate_path_returns_404(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        [$admin, $incident] = $this->seedIncidentWithPaths(includeBranding: false);
        $incident->update(['plate_photo_path' => '../../../.env']);

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.incidents.plate-photo-preview', $incident, false))
            ->assertNotFound();

        $this->assertSame(0, $fake->downloadCalls);
    }

    public function test_branding_preview_returns_404_when_no_branding_path(): void
    {
        Storage::fake('local');
        $admin = $this->makePanelAdmin();
        $recorder = Admin::query()->create([
            'username' => 'rec_inc_br',
            'email' => 'rec-inc-br@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);
        $uuid = (string) Str::uuid();
        $platePath = 'limo_incidents/'.$uuid.'/plate_'.Str::uuid()->toString().'.jpg';
        $incident = LimoIncident::query()->create([
            'incident_uuid' => $uuid,
            'type' => LimoIncident::TYPE_DRIVER_NON_COOPERATIVE,
            'license_plate_snapshot' => 'KO77XX',
            'agency_user_id' => null,
            'agency_name_snapshot' => null,
            'agency_email_snapshot' => null,
            'visible_agency_name' => null,
            'plate_photo_path' => $platePath,
            'branding_photo_path' => null,
            'note' => null,
            'occurred_at' => now(),
            'gps_lat' => null,
            'gps_lng' => null,
            'recorded_by_limo_admin_id' => $recorder->id,
            'device_info' => null,
            'communal_email_sent_at' => null,
            'admin_alert_id' => null,
        ]);
        Storage::disk('local')->put($platePath, 'p');

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.incidents.branding-photo-preview', $incident, false))
            ->assertNotFound();
    }

    /**
     * @return array{0: Admin, 1: LimoIncident, 2: string}
     */
    private function seedIncidentWithPaths(bool $includeBranding): array
    {
        $admin = $this->makePanelAdmin();
        $recorder = Admin::query()->create([
            'username' => 'rec_inc_'.Str::random(5),
            'email' => 'rec-inc-'.Str::random(5).'@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);
        $uuid = (string) Str::uuid();
        $platePath = 'limo_incidents/'.$uuid.'/plate_'.Str::uuid()->toString().'.jpg';
        $brandingPath = $includeBranding
            ? 'limo_incidents/'.$uuid.'/branding_'.Str::uuid()->toString().'.jpg'
            : null;

        $incident = LimoIncident::query()->create([
            'incident_uuid' => $uuid,
            'type' => LimoIncident::TYPE_QR_INSUFFICIENT_FUNDS,
            'license_plate_snapshot' => 'KO12AB',
            'agency_user_id' => null,
            'agency_name_snapshot' => null,
            'agency_email_snapshot' => null,
            'visible_agency_name' => 'Vidljiva agencija',
            'plate_photo_path' => $platePath,
            'branding_photo_path' => $brandingPath,
            'note' => 'Test bilješka',
            'occurred_at' => now(),
            'gps_lat' => '42.4242424',
            'gps_lng' => '18.7687654',
            'recorded_by_limo_admin_id' => $recorder->id,
            'device_info' => null,
            'communal_email_sent_at' => null,
            'admin_alert_id' => null,
        ]);

        return [$admin, $incident, $platePath];
    }

    private function makePanelAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'panel_inc_preview_'.Str::random(4),
            'email' => 'panel-inc-preview-'.Str::random(4).'@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => false,
        ]);
    }
}
