<?php

namespace Tests\Feature\Admin;

use App\Contracts\MegaArchiveClient;
use App\Models\Admin;
use App\Models\ExternalFileArchive;
use App\Models\LimoPickupEvent;
use App\Models\LimoPickupPhoto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MegaArchiveFakeClient;
use Tests\TestCase;

final class LimoPlatePhotoPreviewTest extends TestCase
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

    public function test_admin_can_preview_local_plate_photo_for_plate_source_pickup(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        [$admin, $event, $path] = $this->seedPlatePickupWithPhotoPath();
        Storage::disk('local')->put($path, random_bytes(200));

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.pickups.plate-photo-preview', $event, false))
            ->assertOk();

        $this->assertSame(0, $fake->downloadCalls);
    }

    public function test_admin_preview_restores_archived_plate_photo_when_local_missing(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        [$admin, $event, $path] = $this->seedPlatePickupWithPhotoPath();
        $photo = $event->photos()->where('type', 'plate')->firstOrFail();

        ExternalFileArchive::query()->create([
            'source_table' => (new LimoPickupPhoto)->getTable(),
            'source_id' => $photo->id,
            'source_column' => 'path',
            'context_type' => 'limo_pickup_photo',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'x__limo_pickup_photos_'.$photo->id.'__path__'.Str::uuid()->toString().'.jpg',
            'mega_node_id' => 'n',
            'mega_path' => 'bus.kotor/x.jpg',
            'original_local_path' => $path,
            'local_deleted_at' => now()->subDay(),
            'archived_at' => now()->subDay(),
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'error_message' => null,
        ]);

        $this->assertFalse(Storage::disk('local')->exists($path));

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.pickups.plate-photo-preview', $event, false))
            ->assertOk();

        $this->assertSame(1, $fake->downloadCalls);
        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_preview_restore_does_not_clear_local_deleted_at(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        [$admin, $event, $path] = $this->seedPlatePickupWithPhotoPath();
        $photo = $event->photos()->where('type', 'plate')->firstOrFail();

        $archive = ExternalFileArchive::query()->create([
            'source_table' => (new LimoPickupPhoto)->getTable(),
            'source_id' => $photo->id,
            'source_column' => 'path',
            'context_type' => 'limo_pickup_photo',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'y__limo_pickup_photos_'.$photo->id.'__path__'.Str::uuid()->toString().'.jpg',
            'mega_node_id' => 'n',
            'mega_path' => 'bus.kotor/y.jpg',
            'original_local_path' => $path,
            'local_deleted_at' => now()->subDay(),
            'archived_at' => now()->subDay(),
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'error_message' => null,
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.pickups.plate-photo-preview', $event, false))
            ->assertOk();

        $archive->refresh();
        $this->assertNotNull($archive->local_deleted_at);
        $this->assertNotNull($archive->preview_restored_at);
        $this->assertNotNull($archive->preview_expires_at);
    }

    public function test_non_admin_cannot_access_preview(): void
    {
        Storage::fake('local');
        [$admin, $event, $path] = $this->seedPlatePickupWithPhotoPath();
        Storage::disk('local')->put($path, 'x');

        $this->get(route('admin.limo.pickups.plate-photo-preview', $event, false))
            ->assertRedirect();
    }

    public function test_unsafe_photo_path_returns_404(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        [$admin, $event] = $this->seedPlatePickupWithPhotoPath();
        $photo = $event->photos()->where('type', 'plate')->firstOrFail();
        $photo->update(['path' => '../../../.env']);

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.pickups.plate-photo-preview', $event, false))
            ->assertNotFound();

        $this->assertSame(0, $fake->downloadCalls);
    }

    public function test_legacy_limo_pickup_photos_prefix_still_allowed(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        [$admin, $event, $path] = $this->seedPlatePickupWithPhotoPath('limo_pickup_photos/legacy/plate.jpg');
        Storage::disk('local')->put($path, 'x');

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.pickups.plate-photo-preview', $event, false))
            ->assertOk();
    }

    public function test_rejects_paths_outside_allowed_prefixes(): void
    {
        Storage::fake('local');
        [$admin, $event] = $this->seedPlatePickupWithPhotoPath();
        $event->photos()->where('type', 'plate')->firstOrFail()->update(['path' => 'tmp/not-allowed.jpg']);
        Storage::disk('local')->put('tmp/not-allowed.jpg', 'x');

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.pickups.plate-photo-preview', $event, false))
            ->assertNotFound();
    }

    public function test_non_plate_pickup_preview_route_returns_404(): void
    {
        Storage::fake('local');
        $admin = $this->makePanelAdmin();
        $user = User::factory()->create(['country' => 'ME']);
        $recorder = Admin::query()->create([
            'username' => 'rec_qr',
            'email' => 'rec-qr@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);

        $event = LimoPickupEvent::query()->create([
            'merchant_transaction_id' => (string) Str::uuid(),
            'agency_user_id' => $user->id,
            'agency_name_snapshot' => 'Ag',
            'agency_email_snapshot' => $user->email,
            'agency_country_snapshot' => 'ME',
            'source' => 'qr',
            'qr_token_hash' => null,
            'qr_valid_on' => Carbon::today('Europe/Podgorica'),
            'license_plate_snapshot' => 'KO1',
            'amount_snapshot' => '10.00',
            'service_name_snapshot' => 'Limo',
            'occurred_at' => now(),
            'recorded_by_limo_admin_id' => $recorder->id,
            'status' => 'pending_fiscal',
        ]);

        $this->actingAs($admin, 'panel_admin')
            ->get(route('admin.limo.pickups.plate-photo-preview', $event, false))
            ->assertNotFound();
    }

    public function test_limo_index_shows_preview_link_only_for_plate_source(): void
    {
        Storage::fake('local');
        $admin = $this->makePanelAdmin();
        $this->actingAs($admin, 'panel_admin');

        [, $plateEvent] = $this->seedPlatePickupWithPhotoPath();
        $platePath = $plateEvent->photos()->where('type', 'plate')->firstOrFail()->path;
        Storage::disk('local')->put($platePath, 'x');

        $user = User::factory()->create(['country' => 'ME']);
        $recorder = Admin::query()->create([
            'username' => 'rec_qr2',
            'email' => 'rec-qr2@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);
        LimoPickupEvent::query()->create([
            'merchant_transaction_id' => (string) Str::uuid(),
            'agency_user_id' => $user->id,
            'agency_name_snapshot' => 'QR Ag',
            'agency_email_snapshot' => $user->email,
            'agency_country_snapshot' => 'ME',
            'source' => 'qr',
            'qr_token_hash' => null,
            'qr_valid_on' => Carbon::today('Europe/Podgorica'),
            'license_plate_snapshot' => 'KO2',
            'amount_snapshot' => '11.00',
            'service_name_snapshot' => 'Limo',
            'occurred_at' => now(),
            'recorded_by_limo_admin_id' => $recorder->id,
            'status' => 'pending_fiscal',
        ]);

        $html = $this->get(route('admin.limo.index', [], false))->assertOk()->getContent();
        $this->assertStringContainsString(route('admin.limo.pickups.plate-photo-preview', $plateEvent, false), $html);
        $this->assertSame(1, substr_count($html, 'Slika tablice'));
    }

    /**
     * @return array{0: Admin, 1: LimoPickupEvent, 2: string}
     */
    private function seedPlatePickupWithPhotoPath(?string $path = null): array
    {
        $admin = $this->makePanelAdmin();
        $user = User::factory()->create(['country' => 'ME']);
        $recorder = Admin::query()->create([
            'username' => 'rec_'.Str::random(5),
            'email' => 'rec-'.Str::random(5).'@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);

        $event = LimoPickupEvent::query()->create([
            'merchant_transaction_id' => (string) Str::uuid(),
            'agency_user_id' => $user->id,
            'agency_name_snapshot' => 'Test Ag',
            'agency_email_snapshot' => $user->email,
            'agency_country_snapshot' => 'ME',
            'source' => 'plate',
            'qr_token_hash' => null,
            'qr_valid_on' => Carbon::today('Europe/Podgorica'),
            'license_plate_snapshot' => 'KO99XX',
            'amount_snapshot' => '12.00',
            'service_name_snapshot' => 'Limo',
            'occurred_at' => now(),
            'recorded_by_limo_admin_id' => $recorder->id,
            'status' => 'pending_fiscal',
        ]);

        $resolvedPath = $path ?? ('limo_pickup_evidence/'.$event->id.'/plate_'.Str::uuid()->toString().'.jpg');

        LimoPickupPhoto::query()->create([
            'limo_pickup_event_id' => $event->id,
            'path' => $resolvedPath,
            'type' => 'plate',
        ]);

        return [$admin, $event, $resolvedPath];
    }

    private function makePanelAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'panel_preview_'.Str::random(4),
            'email' => 'panel-preview-'.Str::random(4).'@test.local',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => false,
        ]);
    }
}
