<?php

namespace Tests\Feature\AdminPanel;

use App\Models\Admin;
use App\Models\ExternalFileArchive;
use App\Contracts\MegaArchiveClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MegaArchiveFakeClient;
use Tests\TestCase;

class AdminFailedExternalArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'arch-admin',
            'email' => 'arch-admin@test.local',
            'password' => bcrypt('x'),
            'control_access' => false,
            'admin_access' => true,
        ]);
    }

    public function test_page_lists_failed_archive_rows(): void
    {
        Storage::fake('local');
        Storage::put('a/f1.pdf', '%PDF');
        Storage::put('b/f2.pdf', '%PDF');

        ExternalFileArchive::query()->create([
            'source_table' => 'free_reservation_request_attachments',
            'source_id' => 10,
            'source_column' => 'stored_path',
            'context_type' => 'fzbr_attachment',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'g1.pdf',
            'mega_node_id' => null,
            'mega_path' => null,
            'original_local_path' => 'a/f1.pdf',
            'archived_derivative' => false,
            'derivative_source_path' => null,
            'derivative_options' => null,
            'local_deleted_at' => null,
            'archived_at' => null,
            'status' => ExternalFileArchive::STATUS_FAILED,
            'error_message' => 'e1',
        ]);
        ExternalFileArchive::query()->create([
            'source_table' => 'limo_plate_uploads',
            'source_id' => 20,
            'source_column' => 'path',
            'context_type' => 'limo_plate_upload',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'g2.jpg',
            'mega_node_id' => null,
            'mega_path' => null,
            'original_local_path' => 'b/f2.pdf',
            'archived_derivative' => false,
            'derivative_source_path' => null,
            'derivative_options' => null,
            'local_deleted_at' => null,
            'archived_at' => null,
            'status' => ExternalFileArchive::STATUS_FAILED,
            'error_message' => 'e2',
        ]);

        $admin = $this->makeAdmin();
        $html = $this->actingAs($admin, 'panel_admin')
            ->get(route('panel_admin.archive.failed', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('free_reservation_request_attachments', $html);
        $this->assertStringContainsString('limo_plate_uploads', $html);
        $this->assertStringContainsString('a/f1.pdf', $html);
        $this->assertStringContainsString('b/f2.pdf', $html);
    }

    public function test_uploaded_rows_are_not_listed(): void
    {
        Storage::fake('local');
        ExternalFileArchive::query()->create([
            'source_table' => 't',
            'source_id' => 1,
            'source_column' => 'c',
            'context_type' => 'x',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'up.pdf',
            'mega_node_id' => 'n',
            'mega_path' => 'bus.kotor/up.pdf',
            'original_local_path' => 'gone.pdf',
            'archived_derivative' => false,
            'derivative_source_path' => null,
            'derivative_options' => null,
            'local_deleted_at' => now(),
            'archived_at' => now(),
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'error_message' => null,
        ]);

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'panel_admin')
            ->get(route('panel_admin.archive.failed', [], false))
            ->assertOk()
            ->assertSee('Nema neuspjelih arhiva', false);
    }

    public function test_retry_succeeds_and_row_becomes_uploaded(): void
    {
        Storage::fake('local');
        $path = 'fzbr_docs/1/doc.pdf';
        Storage::put($path, '%PDF-1.4');

        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $row = ExternalFileArchive::query()->create([
            'source_table' => 'free_reservation_request_attachments',
            'source_id' => 5,
            'source_column' => 'stored_path',
            'context_type' => 'fzbr_attachment',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'existing__t_5__stored_path__uuid.pdf',
            'mega_node_id' => null,
            'mega_path' => null,
            'original_local_path' => $path,
            'archived_derivative' => false,
            'derivative_source_path' => null,
            'derivative_options' => null,
            'local_deleted_at' => null,
            'archived_at' => null,
            'status' => ExternalFileArchive::STATUS_FAILED,
            'error_message' => 'prev',
        ]);

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'panel_admin')
            ->post(route('panel_admin.archive.failed.retry', $row, false))
            ->assertRedirect(route('panel_admin.archive.failed', [], false));

        $row->refresh();
        $this->assertSame(ExternalFileArchive::STATUS_UPLOADED, $row->status);
        $this->assertNull($row->error_message);
        $this->assertNotNull($row->mega_path);
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertSame(1, $fake->uploadCalls);
    }

    public function test_retry_fails_and_row_remains_failed(): void
    {
        Storage::fake('local');
        $path = 'x/y.pdf';
        Storage::put($path, '%PDF');

        $fake = new MegaArchiveFakeClient;
        $fake->uploadShouldFail = true;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $row = ExternalFileArchive::query()->create([
            'source_table' => 'free_reservation_request_attachments',
            'source_id' => 7,
            'source_column' => 'stored_path',
            'context_type' => 'fzbr_attachment',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'gen.pdf',
            'mega_node_id' => null,
            'mega_path' => null,
            'original_local_path' => $path,
            'archived_derivative' => false,
            'derivative_source_path' => null,
            'derivative_options' => null,
            'local_deleted_at' => null,
            'archived_at' => null,
            'status' => ExternalFileArchive::STATUS_FAILED,
            'error_message' => 'old_err',
        ]);

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'panel_admin')
            ->post(route('panel_admin.archive.failed.retry', $row, false))
            ->assertRedirect(route('panel_admin.archive.failed', [], false))
            ->assertSessionHas('error');

        $row->refresh();
        $this->assertSame(ExternalFileArchive::STATUS_FAILED, $row->status);
        $this->assertSame('fake_upload_failed', $row->error_message);
        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_retry_returns_error_when_local_file_missing(): void
    {
        Storage::fake('local');

        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        $row = ExternalFileArchive::query()->create([
            'source_table' => 'free_reservation_request_attachments',
            'source_id' => 8,
            'source_column' => 'stored_path',
            'context_type' => 'fzbr_attachment',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'gen.pdf',
            'mega_node_id' => null,
            'mega_path' => null,
            'original_local_path' => 'missing/path.pdf',
            'archived_derivative' => false,
            'derivative_source_path' => null,
            'derivative_options' => null,
            'local_deleted_at' => null,
            'archived_at' => null,
            'status' => ExternalFileArchive::STATUS_FAILED,
            'error_message' => 'x',
        ]);

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'panel_admin')
            ->post(route('panel_admin.archive.failed.retry', $row, false))
            ->assertRedirect(route('panel_admin.archive.failed', [], false))
            ->assertSessionHas('error');

        $this->assertSame(0, $fake->uploadCalls);
    }

    public function test_retry_rejected_when_uploaded_row_exists_for_same_source(): void
    {
        Storage::fake('local');
        $path = 'fzbr_docs/9/doc.pdf';
        Storage::put($path, '%PDF');

        $fake = new MegaArchiveFakeClient;
        $this->app->instance(MegaArchiveClient::class, $fake);

        ExternalFileArchive::query()->create([
            'source_table' => 'free_reservation_request_attachments',
            'source_id' => 99,
            'source_column' => 'stored_path',
            'context_type' => 'fzbr_attachment',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'other.pdf',
            'mega_node_id' => 'n1',
            'mega_path' => 'bus.kotor/other.pdf',
            'original_local_path' => $path,
            'archived_derivative' => false,
            'derivative_source_path' => null,
            'derivative_options' => null,
            'local_deleted_at' => null,
            'archived_at' => now(),
            'status' => ExternalFileArchive::STATUS_UPLOADED,
            'error_message' => null,
        ]);

        $failed = ExternalFileArchive::query()->create([
            'source_table' => 'free_reservation_request_attachments',
            'source_id' => 99,
            'source_column' => 'stored_path',
            'context_type' => 'fzbr_attachment',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'failed.pdf',
            'mega_node_id' => null,
            'mega_path' => null,
            'original_local_path' => $path,
            'archived_derivative' => false,
            'derivative_source_path' => null,
            'derivative_options' => null,
            'local_deleted_at' => null,
            'archived_at' => null,
            'status' => ExternalFileArchive::STATUS_FAILED,
            'error_message' => 'x',
        ]);

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'panel_admin')
            ->post(route('panel_admin.archive.failed.retry', $failed, false))
            ->assertRedirect(route('panel_admin.archive.failed', [], false))
            ->assertSessionHas('error');

        $this->assertSame(0, $fake->uploadCalls);
    }

    public function test_non_admin_cannot_access_page(): void
    {
        $blocked = Admin::query()->create([
            'username' => 'no-panel',
            'email' => 'no-panel@test.local',
            'password' => bcrypt('x'),
            'control_access' => false,
            'admin_access' => false,
        ]);

        $this->actingAs($blocked, 'panel_admin')
            ->get(route('panel_admin.archive.failed', [], false))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_page(): void
    {
        $this->get(route('panel_admin.archive.failed', [], false))
            ->assertRedirect(route('panel_admin.login', [], false));
    }
}
