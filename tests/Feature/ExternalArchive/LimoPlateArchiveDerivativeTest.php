<?php

namespace Tests\Feature\ExternalArchive;

use App\Contracts\MegaArchiveClient;
use App\Models\Admin;
use App\Models\ExternalFileArchive;
use App\Models\LimoPlateUpload;
use App\Services\ExternalArchive\ArchiveDerivativeUpload;
use App\Services\ExternalArchive\ExternalFileArchiveService;
use App\Services\Limo\LimoPlateArchiveDerivativeBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\MegaArchiveFakeClient;
use Tests\TestCase;

class LimoPlateArchiveDerivativeTest extends TestCase
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

    public function test_limo_plate_archive_uploads_smaller_derivative_and_deletes_original_on_success(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'limo_plate_uploads/arc/'.Str::uuid()->toString().'.jpg';
        $absolute = $this->writeLargeTestPlateJpeg(Storage::disk('local')->path($path));
        $originalBytes = (int) filesize($absolute);

        $admin = Admin::query()->create([
            'username' => 'deriv_u',
            'email' => 'deriv-u@test.local',
            'password' => bcrypt('x'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);

        $plate = LimoPlateUpload::query()->create([
            'upload_token' => Str::random(64),
            'path' => $path,
            'uploaded_by_limo_admin_id' => $admin->id,
            'expires_at' => now()->addHour(),
            'consumed_at' => now(),
        ]);

        Artisan::call('files:archive-private', [
            '--source' => 'limo',
            '--limit' => 10,
        ]);

        $this->assertSame(1, $fake->uploadCalls);
        $this->assertNotNull($fake->lastUploadedBytes);
        $this->assertLessThan($originalBytes, $fake->lastUploadedBytes);
        $this->assertFalse(Storage::disk('local')->exists($path));

        $this->assertDatabaseHas('external_file_archives', [
            'source_table' => (new LimoPlateUpload)->getTable(),
            'source_id' => $plate->id,
            'source_column' => 'path',
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'archived_derivative' => 1,
            'derivative_source_path' => $path,
        ]);

        $row = ExternalFileArchive::query()->where('source_id', $plate->id)->first();
        $this->assertNotNull($row);
        $this->assertStringEndsWith('.jpg', (string) $row->generated_file_name);
        $this->assertIsArray($row->derivative_options);
        $this->assertSame(1600, $row->derivative_options['max_long_edge_px'] ?? null);
    }

    public function test_upload_failure_keeps_original_local_file(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $fake->uploadShouldFail = true;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'limo_plate_uploads/fail/'.Str::uuid()->toString().'.jpg';
        $this->writeLargeTestPlateJpeg(Storage::disk('local')->path($path));

        $admin = Admin::query()->create([
            'username' => 'deriv_fail',
            'email' => 'deriv-fail@test.local',
            'password' => bcrypt('x'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);

        LimoPlateUpload::query()->create([
            'upload_token' => Str::random(64),
            'path' => $path,
            'uploaded_by_limo_admin_id' => $admin->id,
            'expires_at' => now()->addHour(),
            'consumed_at' => now(),
        ]);

        Artisan::call('files:archive-private', ['--source' => 'limo', '--limit' => 5]);

        $this->assertSame(1, $fake->uploadCalls);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertDatabaseHas('external_file_archives', [
            'source_column' => 'path',
            'status' => ExternalFileArchive::STATUS_FAILED,
        ]);
    }

    public function test_preview_restores_archived_derivative_to_original_path(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'limo_plate_uploads/preview/'.Str::uuid()->toString().'.jpg';
        $this->writeLargeTestPlateJpeg(Storage::disk('local')->path($path));

        $builder = new LimoPlateArchiveDerivativeBuilder;
        $built = $builder->buildForArchive(Storage::disk('local')->path($path), null);
        $this->assertNotNull($built);

        $derivative = new ArchiveDerivativeUpload(
            uploadAbsolutePath: $built->absolutePath,
            derivativeSourcePath: $path,
            derivativeOptions: $built->options,
            originalBytes: $built->originalBytes,
            archiveBytes: $built->archiveBytes,
            generatedExtension: 'jpg',
        );

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $row = $svc->archiveLocalPrivateFile(
            (new LimoPlateUpload)->getTable(),
            77,
            'path',
            $path,
            'limo_plate_upload',
            $derivative,
        );

        $this->assertSame(ExternalFileArchive::STATUS_UPLOADED, $row->status);
        $this->assertFalse(Storage::disk('local')->exists($path));

        $preview = $svc->ensureLocalPreviewForSource(
            (new LimoPlateUpload)->getTable(),
            77,
            'path',
            $path,
        );

        $this->assertNotNull($preview);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertSame(1, $fake->downloadCalls);
        $this->assertTrue($preview->restoredFromMegaForPreview);
    }

    public function test_fzbr_archive_does_not_set_derivative_metadata(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $path = 'fzbr_docs/1/doc.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4');

        $svc = $this->app->make(ExternalFileArchiveService::class);
        $row = $svc->archiveLocalPrivateFile(
            'free_reservation_request_attachments',
            1,
            'stored_path',
            $path,
            'fzbr_attachment',
        );

        $this->assertTrue($row->archived_derivative === false || $row->archived_derivative === 0);
        $this->assertNull($row->derivative_source_path);
    }

    public function test_derivative_upload_reports_positive_reduction_percent(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        $path = storage_path('framework/cache/limo_deriv_reduction_'.uniqid('', true).'.jpg');
        $this->writeLargeTestPlateJpeg($path);

        $builder = new LimoPlateArchiveDerivativeBuilder;
        $built = $builder->buildForArchive($path, null);
        $this->assertNotNull($built);

        $derivative = new ArchiveDerivativeUpload(
            uploadAbsolutePath: $built->absolutePath,
            derivativeSourcePath: 'limo_plate_uploads/x.png',
            derivativeOptions: $built->options,
            originalBytes: $built->originalBytes,
            archiveBytes: $built->archiveBytes,
        );

        $this->assertGreaterThan(0, $derivative->reductionPercent());
        $this->assertLessThan($built->originalBytes, $built->archiveBytes);

        @unlink($built->absolutePath);
        @unlink($path);
    }

    private function writeLargeTestPlateJpeg(string $absolutePath): string
    {
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $w = 2800;
        $h = 2100;
        $img = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y += 6) {
            for ($x = 0; $x < $w; $x += 6) {
                $c = imagecolorallocate($img, ($x + $y) % 256, ($x * 7) % 256, ($y * 11) % 256);
                imagefilledrectangle($img, $x, $y, min($x + 5, $w - 1), min($y + 5, $h - 1), $c);
            }
        }
        imagejpeg($img, $absolutePath, 95);
        imagedestroy($img);

        return $absolutePath;
    }
}
