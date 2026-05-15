<?php

namespace Tests\Feature\ExternalArchive;

use App\Models\ExternalFileArchive;
use App\Models\LimoPickupPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CleanupPreviewArchiveCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_removes_expired_preview_file_and_clears_preview_columns(): void
    {
        Storage::fake('local');
        $path = 'limo_pickup_evidence/1/clean-plate.jpg';
        Storage::disk('local')->put($path, 'preview-body');

        $archive = ExternalFileArchive::query()->create([
            'source_table' => (new LimoPickupPhoto)->getTable(),
            'source_id' => 1,
            'source_column' => 'path',
            'context_type' => 'limo_pickup_photo',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'g__limo_1__path__'.Str::uuid()->toString().'.jpg',
            'mega_node_id' => 'n',
            'mega_path' => 'bus.kotor/g.jpg',
            'original_local_path' => $path,
            'local_deleted_at' => now()->subDays(2),
            'archived_at' => now()->subDays(2),
            'preview_restored_at' => now()->subHours(2),
            'preview_expires_at' => now()->subMinute(),
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'error_message' => null,
        ]);

        Artisan::call('files:cleanup-preview-cache');

        $this->assertFalse(Storage::disk('local')->exists($path));
        $archive->refresh();
        $this->assertNull($archive->preview_restored_at);
        $this->assertNull($archive->preview_expires_at);
        $this->assertSame(ExternalFileArchive::STATUS_UPLOADED, $archive->status);
        $this->assertNotNull($archive->local_deleted_at);
    }

    public function test_cleanup_removes_expired_preview_for_limo_incident_plate_path(): void
    {
        Storage::fake('local');
        $path = 'limo_incidents/uuid-1/preview-plate.jpg';
        Storage::disk('local')->put($path, 'inc-preview-body');

        $archive = ExternalFileArchive::query()->create([
            'source_table' => 'limo_incidents',
            'source_id' => 99,
            'source_column' => 'plate_photo_path',
            'context_type' => 'limo_incident_plate',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'g__inc_99__plate__'.Str::uuid()->toString().'.jpg',
            'mega_node_id' => 'n',
            'mega_path' => 'bus.kotor/incp.jpg',
            'original_local_path' => $path,
            'local_deleted_at' => now()->subDays(2),
            'archived_at' => now()->subDays(2),
            'preview_restored_at' => now()->subHours(2),
            'preview_expires_at' => now()->subMinute(),
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'error_message' => null,
        ]);

        Artisan::call('files:cleanup-preview-cache');

        $this->assertFalse(Storage::disk('local')->exists($path));
        $archive->refresh();
        $this->assertNull($archive->preview_expires_at);
        $this->assertNotNull($archive->local_deleted_at);
    }

    public function test_cleanup_does_not_delete_locally_retained_archives(): void
    {
        Storage::fake('local');
        $path = 'limo_pickup_evidence/2/keep-plate.jpg';
        Storage::disk('local')->put($path, 'keep-local');

        $archive = ExternalFileArchive::query()->create([
            'source_table' => (new LimoPickupPhoto)->getTable(),
            'source_id' => 2,
            'source_column' => 'path',
            'context_type' => 'limo_pickup_photo',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'h__limo_2__path__'.Str::uuid()->toString().'.jpg',
            'mega_node_id' => 'n',
            'mega_path' => 'bus.kotor/h.jpg',
            'original_local_path' => $path,
            'local_deleted_at' => null,
            'archived_at' => now()->subDay(),
            'preview_restored_at' => now()->subHour(),
            'preview_expires_at' => now()->subMinute(),
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'error_message' => null,
        ]);

        Artisan::call('files:cleanup-preview-cache');

        $this->assertTrue(Storage::disk('local')->exists($path));
        $archive->refresh();
        $this->assertNotNull($archive->preview_restored_at);
    }
}
