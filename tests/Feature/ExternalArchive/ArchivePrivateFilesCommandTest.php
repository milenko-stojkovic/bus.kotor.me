<?php

namespace Tests\Feature\ExternalArchive;

use App\Contracts\MegaArchiveClient;
use App\Models\Admin;
use App\Models\ExternalFileArchive;
use App\Models\FreeReservationRequest;
use App\Models\FreeReservationRequestAttachment;
use App\Models\LimoPickupEvent;
use App\Models\LimoPickupPhoto;
use App\Models\LimoPlateUpload;
use App\Models\ListOfTimeSlot;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\ExternalArchive\MegaDiagnoseService;
use App\Support\OperationalHeartbeatCache;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\MegaArchiveFakeClient;
use Tests\TestCase;

class ArchivePrivateFilesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00', 'Europe/Podgorica'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dry_run_does_not_upload_or_delete(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        [, , $relPath] = $this->makeFulfilledFzbrWithAttachmentFile();

        Artisan::call('files:archive-private', [
            '--source' => 'fzbr',
            '--dry-run' => true,
            '--limit' => 10,
        ]);

        $this->assertSame(0, $fake->uploadCalls);
        $this->assertTrue(Storage::disk('local')->exists($relPath));
        $this->assertSame(0, ExternalFileArchive::query()->count());
    }

    public function test_operational_heartbeat_cache_after_successful_dry_run(): void
    {
        Cache::flush();
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $this->makeFulfilledFzbrWithAttachmentFile();

        Artisan::call('files:archive-private', [
            '--source' => 'fzbr',
            '--dry-run' => true,
            '--limit' => 10,
        ]);

        $this->assertNotNull(Cache::get(OperationalHeartbeatCache::ARCHIVE_PRIVATE_LAST_RUN_AT));
        $this->assertNotNull(Cache::get(OperationalHeartbeatCache::ARCHIVE_PRIVATE_LAST_OK_AT));
        $raw = Cache::get(OperationalHeartbeatCache::ARCHIVE_PRIVATE_LAST_SUMMARY);
        $this->assertIsString($raw);
        $summary = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('fzbr', $summary['source']);
        $this->assertTrue($summary['dry_run']);
        $this->assertArrayHasKey('scanned', $summary);
        $this->assertArrayHasKey('archived', $summary);
        $this->assertArrayHasKey('failed', $summary);
        $this->assertArrayHasKey('skipped', $summary);
        $this->assertArrayHasKey('timestamp', $summary);
    }

    public function test_command_archives_fzbr_attachment(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        [, $att, $relPath] = $this->makeFulfilledFzbrWithAttachmentFile();

        Artisan::call('files:archive-private', [
            '--source' => 'fzbr',
            '--limit' => 10,
        ]);

        $this->assertSame(1, $fake->uploadCalls);
        $this->assertFalse(Storage::disk('local')->exists($relPath));
        $this->assertDatabaseHas('external_file_archives', [
            'source_table' => (new FreeReservationRequestAttachment)->getTable(),
            'source_id' => $att->id,
            'source_column' => 'stored_path',
            'status' => ExternalFileArchive::STATUS_UPLOADED,
        ]);
    }

    public function test_already_uploaded_is_skipped(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        [, $att, $relPath] = $this->makeFulfilledFzbrWithAttachmentFile();

        ExternalFileArchive::query()->create([
            'source_table' => (new FreeReservationRequestAttachment)->getTable(),
            'source_id' => $att->id,
            'source_column' => 'stored_path',
            'context_type' => 'fzbr_attachment',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'existing__free_reservation_request_attachments_'.$att->id.'__stored_path__00000000-0000-4000-8000-000000000001.pdf',
            'mega_node_id' => 'x',
            'mega_path' => 'bus.kotor/existing.pdf',
            'original_local_path' => $relPath,
            'local_deleted_at' => now(),
            'archived_at' => now(),
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'error_message' => null,
        ]);

        Artisan::call('files:archive-private', [
            '--source' => 'fzbr',
            '--limit' => 10,
        ]);

        $this->assertSame(0, $fake->uploadCalls);
        $this->assertTrue(Storage::disk('local')->exists($relPath));
    }

    public function test_all_source_reports_failed_not_archived_when_mega_upload_fails_and_skips_other_categories(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $fake->uploadShouldFail = true;
        $this->app->instance(MegaArchiveClient::class, $fake);

        [, $fzbrAtt, $fzbrPath] = $this->makeFulfilledFzbrWithAttachmentFile();

        $admin = Admin::query()->create([
            'username' => 'arch_plate_u',
            'email' => 'arch-plate-u@test.local',
            'password' => bcrypt('x'),
            'control_access' => false,
            'admin_access' => true,
            'limo_access' => true,
        ]);
        $platePath = 'limo_plate_uploads/9001/plate.jpg';
        Storage::disk('local')->put($platePath, 'x');
        $plate = LimoPlateUpload::query()->create([
            'upload_token' => Str::random(64),
            'path' => $platePath,
            'uploaded_by_limo_admin_id' => $admin->id,
            'expires_at' => now()->addHour(),
            'consumed_at' => now(),
        ]);
        ExternalFileArchive::query()->create([
            'source_table' => (new LimoPlateUpload)->getTable(),
            'source_id' => (int) $plate->id,
            'source_column' => 'path',
            'context_type' => 'limo_plate_upload',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'ex__limo_plate_'.$plate->id.'__'.Str::uuid()->toString().'.jpg',
            'mega_node_id' => 'n',
            'mega_path' => 'bus.kotor/p.jpg',
            'original_local_path' => $platePath,
            'local_deleted_at' => now(),
            'archived_at' => now(),
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'error_message' => null,
        ]);

        $user = User::factory()->create(['country' => 'ME']);
        $recorder = Admin::query()->create([
            'username' => 'arch_lim_rec',
            'email' => 'arch-lim-rec@test.local',
            'password' => bcrypt('x'),
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
            'source' => 'plate',
            'qr_token_hash' => null,
            'qr_valid_on' => Carbon::today('Europe/Podgorica'),
            'license_plate_snapshot' => 'KO1',
            'amount_snapshot' => '10.00',
            'service_name_snapshot' => 'Limo',
            'occurred_at' => now(),
            'recorded_by_limo_admin_id' => $recorder->id,
            'status' => 'pending_fiscal',
            'invoice_email_sent_at' => now(),
            'email_sent' => LimoPickupEvent::EMAIL_SENT,
        ]);
        $missingPhotoPath = 'limo_pickup_evidence/'.$event->id.'/missing.jpg';
        LimoPickupPhoto::query()->create([
            'limo_pickup_event_id' => $event->id,
            'path' => $missingPhotoPath,
            'type' => 'plate',
        ]);

        Artisan::call('files:archive-private', [
            '--source' => 'all',
            '--limit' => 1,
        ]);
        $out = Artisan::output();

        $this->assertStringContainsString('Scanned: 3', $out);
        $this->assertStringContainsString('Archived: 0', $out);
        $this->assertStringContainsString('Failed: 1', $out);
        $this->assertStringContainsString('Skipped: 2', $out);

        $this->assertTrue(Storage::disk('local')->exists($fzbrPath));
        $this->assertDatabaseHas('external_file_archives', [
            'source_table' => (new FreeReservationRequestAttachment)->getTable(),
            'source_id' => $fzbrAtt->id,
            'source_column' => 'stored_path',
            'status' => ExternalFileArchive::STATUS_FAILED,
        ]);
        $this->assertSame(1, $fake->uploadCalls);
    }

    public function test_require_mega_health_skips_when_mega_diagnose_unhealthy(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $partial = Mockery::mock(new MegaDiagnoseService);
        $partial->shouldReceive('run')->once()->andReturn([
            'ok' => false,
            'email_present' => true,
            'password_present' => true,
            'base_folder' => 'bus.kotor',
            'login_ok' => false,
            'folder_found' => false,
            'node_version' => null,
            'root_children_sample' => [],
            'error' => 'ENOENT',
            'node_binary' => 'node',
            'user_agent' => 'x',
        ]);
        $this->instance(MegaDiagnoseService::class, $partial);

        [, , $relPath] = $this->makeFulfilledFzbrWithAttachmentFile();

        Artisan::call('files:archive-private', [
            '--source' => 'fzbr',
            '--limit' => 10,
            '--require-mega-health' => true,
        ]);
        $out = Artisan::output();

        $this->assertStringContainsString('Skipped: MEGA not ready', $out);
        $this->assertSame(0, $fake->uploadCalls);
        $this->assertTrue(Storage::disk('local')->exists($relPath));
        $this->assertSame(0, ExternalFileArchive::query()->count());
    }

    public function test_require_mega_health_proceeds_when_mega_diagnose_ok(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $partial = Mockery::mock(new MegaDiagnoseService);
        $partial->shouldReceive('run')->once()->andReturn([
            'ok' => true,
            'email_present' => true,
            'password_present' => true,
            'base_folder' => 'bus.kotor',
            'login_ok' => true,
            'folder_found' => true,
            'node_version' => '20',
            'root_children_sample' => [],
            'error' => '',
            'node_binary' => 'node',
            'user_agent' => 'x',
        ]);
        $this->instance(MegaDiagnoseService::class, $partial);

        [, , $relPath] = $this->makeFulfilledFzbrWithAttachmentFile();

        Artisan::call('files:archive-private', [
            '--source' => 'fzbr',
            '--limit' => 10,
            '--require-mega-health' => true,
        ]);

        $this->assertSame(1, $fake->uploadCalls);
        $this->assertFalse(Storage::disk('local')->exists($relPath));
    }

    public function test_limit_is_applied_per_fzbr_category(): void
    {
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $this->makeFulfilledFzbrWithAttachmentFile();
        $this->makeFulfilledFzbrWithAttachmentFile();

        Artisan::call('files:archive-private', [
            '--source' => 'fzbr',
            '--limit' => 1,
        ]);
        $out = Artisan::output();

        $this->assertStringContainsString('Scanned: 1', $out);
        $this->assertStringContainsString('Archived: 1', $out);
        $this->assertSame(1, $fake->uploadCalls);
        $this->assertSame(1, ExternalFileArchive::query()->where('status', ExternalFileArchive::STATUS_UPLOADED)->count());
    }

    public function test_summary_is_logged_after_run(): void
    {
        Event::fake([MessageLogged::class]);
        Storage::fake('local');
        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $this->makeFulfilledFzbrWithAttachmentFile();

        Artisan::call('files:archive-private', [
            '--source' => 'fzbr',
            '--limit' => 10,
        ]);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $e): bool {
            return $e->level === 'info'
                && $e->message === 'files_archive_private_summary'
                && ($e->context['scanned'] ?? 0) >= 1
                && ($e->context['archived'] ?? 0) >= 1;
        });
    }

    /**
     * @return array{0: FreeReservationRequest, 1: FreeReservationRequestAttachment, 2: string}
     */
    private function makeFulfilledFzbrWithAttachmentFile(): array
    {
        $slotA = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $slotB = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $vt = VehicleType::query()->create(['price' => 10]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'Autobus',
            'description' => 'x',
        ]);

        $req = FreeReservationRequest::query()->create([
            'locale' => 'cg',
            'institution_name' => 'Test',
            'institution_email' => 't@example.com',
            'institution_phone' => '+382000',
            'reservation_date' => Carbon::now()->addDays(2)->toDateString(),
            'drop_off_time_slot_id' => $slotA->id,
            'pick_up_time_slot_id' => $slotB->id,
            'country' => 'ME',
            'status' => FreeReservationRequest::STATUS_FULFILLED,
        ]);

        $relPath = 'fzbr_docs/'.$req->id.'/doc.pdf';
        Storage::disk('local')->put($relPath, '%PDF-1.4 fake');

        $att = FreeReservationRequestAttachment::query()->create([
            'request_id' => $req->id,
            'original_name' => 'doc.pdf',
            'stored_path' => $relPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => 9,
        ]);

        return [$req, $att, $relPath];
    }
}
