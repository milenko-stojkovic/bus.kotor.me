<?php

namespace Tests\Feature\ExternalArchive;

use App\Contracts\MegaArchiveClient;
use App\Models\ExternalFileArchive;
use App\Services\ExternalArchive\ArchiveFilenameGenerator;
use App\Services\ExternalArchive\ExternalFileArchiveService;
use App\Services\ExternalArchive\MegaUploadResult;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\MegaArchiveFakeClient;
use Tests\TestCase;

class ExternalFileArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_success_creates_row_uploads_and_deletes_local_file(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'limo_plate_uploads/test.jpg';
        Storage::disk('local')->put($path, 'binary');

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $row = $svc->archiveLocalPrivateFile('limo_plate_uploads', 1, 'path', $path, 'limo_plate_upload');

        $this->assertSame(1, $fake->uploadCalls);
        $this->assertSame(ExternalFileArchive::STATUS_UPLOADED, $row->status);
        $this->assertNotNull($row->archived_at);
        $this->assertNotNull($row->local_deleted_at);
        $this->assertSame($path, $row->original_local_path);
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertDatabaseHas('external_file_archives', [
            'id' => $row->id,
            'status' => ExternalFileArchive::STATUS_UPLOADED,
        ]);
    }

    public function test_upload_failure_keeps_local_and_marks_failed(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $fake->uploadShouldFail = true;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'limo_plate_uploads/fail.jpg';
        Storage::disk('local')->put($path, 'x');

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $row = $svc->archiveLocalPrivateFile('limo_plate_uploads', 2, 'path', $path, 'limo_plate_upload');

        $this->assertSame(ExternalFileArchive::STATUS_FAILED, $row->status);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertNull($row->local_deleted_at);
        $this->assertSame(1, $fake->uploadCalls);
    }

    public function test_transient_upload_failure_succeeds_on_retry(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $fake->uploadResultsQueue = [
            new MegaUploadResult(false, null, null, 'ETIMEDOUT: connection timed out'),
            new MegaUploadResult(true, 'node-recovered', 'bus.kotor/recovered.pdf', null),
        ];
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'limo_plate_uploads/retry-ok.jpg';
        Storage::disk('local')->put($path, 'binary');

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $row = $svc->archiveLocalPrivateFile('limo_plate_uploads', 42, 'path', $path, 'limo_plate_upload');

        $this->assertSame(2, $fake->uploadCalls);
        $this->assertSame(ExternalFileArchive::STATUS_UPLOADED, $row->status);
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertNotNull($row->local_deleted_at);
    }

    public function test_permanent_login_failure_does_not_retry_upload(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $fake->uploadResultsQueue = [
            new MegaUploadResult(false, null, null, 'Wrong password for account'),
        ];
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'limo_plate_uploads/bad-login.jpg';
        Storage::disk('local')->put($path, 'x');

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $row = $svc->archiveLocalPrivateFile('limo_plate_uploads', 43, 'path', $path, 'limo_plate_upload');

        $this->assertSame(1, $fake->uploadCalls);
        $this->assertSame(ExternalFileArchive::STATUS_FAILED, $row->status);
        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_transient_upload_exhausts_retries_then_failed(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $fake->uploadResultsQueue = [
            new MegaUploadResult(false, null, null, 'ECONNRESET'),
            new MegaUploadResult(false, null, null, 'ECONNRESET'),
            new MegaUploadResult(false, null, null, 'ECONNRESET'),
        ];
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'limo_plate_uploads/exhaust.jpg';
        Storage::disk('local')->put($path, 'x');

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $row = $svc->archiveLocalPrivateFile('limo_plate_uploads', 44, 'path', $path, 'limo_plate_upload');

        $this->assertSame(3, $fake->uploadCalls);
        $this->assertSame(ExternalFileArchive::STATUS_FAILED, $row->status);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertStringContainsString('ECONNRESET', (string) $row->error_message);
    }

    public function test_second_call_returns_existing_uploaded_without_reupload(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'limo_plate_uploads/twice.jpg';
        Storage::disk('local')->put($path, 'x');

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $first = $svc->archiveLocalPrivateFile('limo_plate_uploads', 9, 'path', $path, 'limo_plate_upload');
        $this->assertSame(1, $fake->uploadCalls);

        $second = $svc->archiveLocalPrivateFile('limo_plate_uploads', 9, 'path', $path, 'limo_plate_upload');
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $fake->uploadCalls);
    }

    public function test_generated_names_are_unique_and_safe(): void
    {
        $a = ArchiveFilenameGenerator::generate('ctx', 'tbl', 1, 'col', 'p/file Name.JPG');
        $b = ArchiveFilenameGenerator::generate('ctx', 'tbl', 1, 'col', 'p/file Name.JPG');
        $this->assertNotSame($a, $b);
        $this->assertStringContainsString('.jpg', $a);
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+__[a-z0-9_]+_1__[a-z0-9_]+__[a-f0-9\-]{36}\.jpg$/', $a);
    }

    public function test_timestamps_on_row(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'x/a.bin';
        Storage::disk('local')->put($path, 'z');

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $row = $svc->archiveLocalPrivateFile('t', 3, 'c', $path, null);

        $this->assertNotNull($row->created_at);
        $this->assertNotNull($row->updated_at);
        $this->assertNotNull($row->archived_at);
        $this->assertNotNull($row->local_deleted_at);
    }

    public function test_restore_from_mega_clears_local_deleted_at(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'restore/me.pdf';
        Storage::disk('local')->put($path, 'orig');

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $row = $svc->archiveLocalPrivateFile('free_reservation_request_attachments', 5, 'stored_path', $path, 'fzbr_attachment');
        $this->assertFalse(Storage::disk('local')->exists($path));

        $svc->restoreFromMega($row->refresh());
        $row->refresh();
        $this->assertNull($row->local_deleted_at);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertSame(1, $fake->downloadCalls);
    }

    public function test_restore_passes_generated_file_name_to_client(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'restore/nopath.bin';
        Storage::disk('local')->put($path, 'x');

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $row = $svc->archiveLocalPrivateFile('t', 7, 'c', $path, 'ctx');

        $row->update([
            'mega_path' => null,
            'mega_node_id' => null,
        ]);

        $svc->restoreFromMega($row->refresh());

        $row->refresh();
        $this->assertSame($row->generated_file_name, $fake->lastDownloadGeneratedFileName);
        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_ensure_local_preview_returns_null_when_mega_download_fails(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $fake->downloadShouldFail = true;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'limo_pickup_evidence/99/preview-fail.jpg';
        $arch = ExternalFileArchive::query()->create([
            'source_table' => 'limo_pickup_photos',
            'source_id' => 99,
            'source_column' => 'path',
            'context_type' => 'limo_pickup_photo',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'g__limo_99__path__'.Str::uuid()->toString().'.jpg',
            'mega_node_id' => 'n',
            'mega_path' => 'bus.kotor/x.jpg',
            'original_local_path' => $path,
            'local_deleted_at' => now()->subDay(),
            'archived_at' => now()->subDay(),
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'error_message' => null,
        ]);
        $deletedAtBefore = $arch->local_deleted_at;

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $this->assertNull($svc->ensureLocalPreviewForSource('limo_pickup_photos', 99, 'path', $path));
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertSame(1, $fake->downloadCalls);

        $arch->refresh();
        $this->assertTrue($arch->local_deleted_at->equalTo($deletedAtBefore));
        $this->assertSame(ExternalFileArchive::STATUS_UPLOADED, $arch->status);
    }

    public function test_preview_transient_download_succeeds_on_retry(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $fake->downloadResultsQueue = [
            new MegaUploadResult(false, null, null, 'socket hang up'),
            new MegaUploadResult(true, null, 'bus.kotor/x.jpg', null),
        ];
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'limo_pickup_evidence/100/preview-retry.jpg';
        $arch = ExternalFileArchive::query()->create([
            'source_table' => 'limo_pickup_photos',
            'source_id' => 100,
            'source_column' => 'path',
            'context_type' => 'limo_pickup_photo',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'g__limo_100__path__'.Str::uuid()->toString().'.jpg',
            'mega_node_id' => 'n',
            'mega_path' => 'bus.kotor/x.jpg',
            'original_local_path' => $path,
            'local_deleted_at' => now()->subDay(),
            'archived_at' => now()->subDay(),
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'error_message' => null,
        ]);
        $deletedAtBefore = $arch->local_deleted_at->copy();

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $preview = $svc->ensureLocalPreviewForSource('limo_pickup_photos', 100, 'path', $path);

        $this->assertNotNull($preview);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertSame(2, $fake->downloadCalls);

        $arch->refresh();
        $this->assertTrue($arch->local_deleted_at->equalTo($deletedAtBefore));
        $this->assertNotNull($arch->preview_restored_at);
        $this->assertSame(ExternalFileArchive::STATUS_UPLOADED, $arch->status);
    }

    public function test_preview_permanent_error_fails_without_extra_retries(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $fake->downloadResultsQueue = [
            new MegaUploadResult(false, null, null, 'Wrong password for MEGA'),
        ];
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'limo_pickup_evidence/101/preview-bad.jpg';
        ExternalFileArchive::query()->create([
            'source_table' => 'limo_pickup_photos',
            'source_id' => 101,
            'source_column' => 'path',
            'context_type' => 'limo_pickup_photo',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'g__limo_101__path__'.Str::uuid()->toString().'.jpg',
            'mega_node_id' => 'n',
            'mega_path' => 'bus.kotor/x.jpg',
            'original_local_path' => $path,
            'local_deleted_at' => now()->subDay(),
            'archived_at' => now()->subDay(),
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'error_message' => null,
        ]);

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $this->assertNull($svc->ensureLocalPreviewForSource('limo_pickup_photos', 101, 'path', $path));
        $this->assertSame(1, $fake->downloadCalls);
    }
}
