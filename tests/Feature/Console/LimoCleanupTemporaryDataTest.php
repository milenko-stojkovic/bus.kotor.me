<?php

namespace Tests\Feature\Console;

use App\Models\Admin;
use App\Models\LimoPlateUpload;
use App\Models\LimoQrToken;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LimoCleanupTemporaryDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-04 12:00:00', 'Europe/Podgorica'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_deletes_old_limo_qr_tokens_but_keeps_today(): void
    {
        $agencyUserId = \App\Models\User::factory()->create()->id;

        LimoQrToken::query()->create([
            'agency_user_id' => $agencyUserId,
            'token_hash' => hash('sha256', 'old'),
            'encrypted_token' => encrypt('old'),
            'valid_on' => Carbon::yesterday('Europe/Podgorica'),
        ]);

        LimoQrToken::query()->create([
            'agency_user_id' => $agencyUserId,
            'token_hash' => hash('sha256', 'today'),
            'encrypted_token' => encrypt('today'),
            'valid_on' => Carbon::today('Europe/Podgorica'),
        ]);

        $this->artisan('limo:cleanup-temporary-data')->assertSuccessful();

        $this->assertSame(1, LimoQrToken::query()->count());
        $this->assertSame(hash('sha256', 'today'), LimoQrToken::query()->value('token_hash'));
    }

    public function test_deletes_expired_unconsumed_plate_uploads_and_files(): void
    {
        Storage::fake('local');
        $admin = Admin::query()->create([
            'username' => 'cleanup_plate',
            'email' => 'cleanup-plate@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => false,
            'limo_access' => true,
        ]);

        $path = 'limo_plate_uploads/expired.jpg';
        Storage::disk('local')->put($path, 'fake-image');

        LimoPlateUpload::query()->create([
            'upload_token' => Str::random(48),
            'path' => $path,
            'uploaded_by_limo_admin_id' => $admin->id,
            'expires_at' => now()->subMinutes(5),
            'consumed_at' => null,
        ]);

        $this->artisan('limo:cleanup-temporary-data')->assertSuccessful();

        $this->assertSame(0, LimoPlateUpload::query()->count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_does_not_delete_unexpired_upload(): void
    {
        Storage::fake('local');
        $admin = Admin::query()->create([
            'username' => 'cleanup_keep',
            'email' => 'cleanup-keep@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => false,
            'limo_access' => true,
        ]);

        $path = 'limo_plate_uploads/fresh.jpg';
        Storage::disk('local')->put($path, 'x');

        LimoPlateUpload::query()->create([
            'upload_token' => Str::random(48),
            'path' => $path,
            'uploaded_by_limo_admin_id' => $admin->id,
            'expires_at' => now()->addHour(),
            'consumed_at' => null,
        ]);

        $this->artisan('limo:cleanup-temporary-data')->assertSuccessful();

        $this->assertSame(1, LimoPlateUpload::query()->count());
        Storage::disk('local')->assertExists($path);
    }

    public function test_does_not_delete_consumed_upload_even_if_expired(): void
    {
        Storage::fake('local');
        $admin = Admin::query()->create([
            'username' => 'cleanup_cons',
            'email' => 'cleanup-cons@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => false,
            'limo_access' => true,
        ]);

        $path = 'limo_pickup_evidence/99/plate.jpg';
        Storage::disk('local')->put($path, 'evidence');

        LimoPlateUpload::query()->create([
            'upload_token' => Str::random(48),
            'path' => $path,
            'uploaded_by_limo_admin_id' => $admin->id,
            'expires_at' => now()->subDay(),
            'consumed_at' => now()->subHour(),
        ]);

        $this->artisan('limo:cleanup-temporary-data')->assertSuccessful();

        $this->assertSame(1, LimoPlateUpload::query()->count());
        Storage::disk('local')->assertExists($path);
    }

    public function test_safe_when_upload_file_already_missing(): void
    {
        Storage::fake('local');
        $admin = Admin::query()->create([
            'username' => 'cleanup_miss',
            'email' => 'cleanup-miss@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => false,
            'limo_access' => true,
        ]);

        $path = 'limo_plate_uploads/ghost.jpg';

        LimoPlateUpload::query()->create([
            'upload_token' => Str::random(48),
            'path' => $path,
            'uploaded_by_limo_admin_id' => $admin->id,
            'expires_at' => now()->subMinute(),
            'consumed_at' => null,
        ]);

        $this->artisan('limo:cleanup-temporary-data')->assertSuccessful();

        $this->assertSame(0, LimoPlateUpload::query()->count());
        Storage::disk('local')->assertMissing($path);
    }
}
