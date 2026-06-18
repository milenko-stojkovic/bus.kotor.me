<?php

namespace Tests\Feature\AdminPanel;

use App\Console\Commands\AlertsSystemHealthCommand;
use App\Models\AdminAlert;
use App\Models\ExternalFileArchive;
use App\Models\ListOfTimeSlot;
use App\Models\Reservation;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\ExternalArchive\MegaDiagnoseService;
use App\Support\OperationalHeartbeatCache;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AdminOperationalHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * MegaDiagnoseService is final; bind a partial mock on a real instance.
     */
    protected function bindMegaDiagnoseRun(array $runReturn): void
    {
        $partial = Mockery::mock(new MegaDiagnoseService);
        $partial->shouldReceive('run')->andReturn($runReturn);
        $this->instance(MegaDiagnoseService::class, $partial);
    }

    protected function megaSkip(): array
    {
        return [
            'ok' => true,
            'email_present' => false,
            'password_present' => false,
            'base_folder' => 'bus.kotor',
            'login_ok' => false,
            'folder_found' => false,
            'node_version' => null,
            'root_children_sample' => [],
            'error' => '',
            'node_binary' => 'node',
            'user_agent' => 'x',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-05-15 08:00:00', 'Europe/Podgorica'));
        config(['queue.default' => 'database']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        parent::tearDown();
    }

    private function insertStalePendingJob(int $ageMinutes = 10): void
    {
        $ts = now()->subMinutes($ageMinutes)->getTimestamp();
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{"displayName":"TestJob"}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $ts,
            'created_at' => $ts,
        ]);
    }

    public function test_first_stale_detection_does_not_create_queue_worker_down_alert(): void
    {
        $this->bindMegaDiagnoseRun($this->megaSkip());
        $this->insertStalePendingJob(10);

        $this->artisan('alerts:system-health')->assertSuccessful();

        $this->assertSame(0, AdminAlert::query()->where('type', 'queue_worker_down')->count());
        $this->assertTrue(Cache::has(AlertsSystemHealthCommand::CACHE_KEY_QUEUE_STALE_FIRST_SEEN));
        $this->assertSame(0, AdminAlert::query()->where('type', 'system_health_daily')->count());
    }

    public function test_second_stale_after_confirm_window_creates_queue_worker_down_alert(): void
    {
        $this->bindMegaDiagnoseRun($this->megaSkip());
        $this->insertStalePendingJob(10);

        $this->artisan('alerts:system-health')->assertSuccessful();
        $this->assertSame(0, AdminAlert::query()->where('type', 'queue_worker_down')->count());

        Carbon::setTestNow(now()->addMinutes(3));
        $this->artisan('alerts:system-health')->assertSuccessful();

        $this->assertSame(1, AdminAlert::query()->where('type', 'queue_worker_down')->count());
        $alert = AdminAlert::query()->where('type', 'queue_worker_down')->first();
        $this->assertStringContainsString('dva ciklusa', $alert->message);
        $this->assertStringContainsString('Queue connection: database', $alert->message);
        $this->assertStringContainsString('ne automatski restartuje', $alert->message);
        $this->assertFalse(Cache::has(AlertsSystemHealthCommand::CACHE_KEY_QUEUE_STALE_FIRST_SEEN));
    }

    public function test_second_run_before_confirm_window_does_not_create_alert(): void
    {
        $this->bindMegaDiagnoseRun($this->megaSkip());
        $this->insertStalePendingJob(10);

        $this->artisan('alerts:system-health')->assertSuccessful();

        Carbon::setTestNow(now()->addMinute());
        $this->artisan('alerts:system-health')->assertSuccessful();

        $this->assertSame(0, AdminAlert::query()->where('type', 'queue_worker_down')->count());
        $this->assertTrue(Cache::has(AlertsSystemHealthCommand::CACHE_KEY_QUEUE_STALE_FIRST_SEEN));
    }

    public function test_healthy_queue_clears_stale_marker(): void
    {
        $this->bindMegaDiagnoseRun($this->megaSkip());
        $this->insertStalePendingJob(10);
        $this->artisan('alerts:system-health')->assertSuccessful();
        $this->assertTrue(Cache::has(AlertsSystemHealthCommand::CACHE_KEY_QUEUE_STALE_FIRST_SEEN));

        DB::table('jobs')->truncate();
        $this->artisan('alerts:system-health')->assertSuccessful();

        $this->assertFalse(Cache::has(AlertsSystemHealthCommand::CACHE_KEY_QUEUE_STALE_FIRST_SEEN));
        $this->assertSame(0, AdminAlert::query()->where('type', 'queue_worker_down')->count());
    }

    public function test_duplicate_queue_worker_down_not_created_while_open_alert_exists(): void
    {
        $this->bindMegaDiagnoseRun($this->megaSkip());
        $this->insertStalePendingJob(10);

        $this->artisan('alerts:system-health')->assertSuccessful();
        Carbon::setTestNow(now()->addMinutes(3));
        $this->artisan('alerts:system-health')->assertSuccessful();
        $this->assertSame(1, AdminAlert::query()->where('type', 'queue_worker_down')->count());

        Carbon::setTestNow(now()->addMinute());
        $this->insertStalePendingJob(10);

        $this->artisan('alerts:system-health')->assertSuccessful();
        Carbon::setTestNow(now()->addMinutes(3));
        $this->artisan('alerts:system-health')->assertSuccessful();

        $this->assertSame(1, AdminAlert::query()->where('type', 'queue_worker_down')->count());
    }

    public function test_non_database_queue_connection_skips_database_stale_check(): void
    {
        $this->bindMegaDiagnoseRun($this->megaSkip());
        config(['queue.default' => 'sync']);
        $this->insertStalePendingJob(10);

        $this->artisan('alerts:system-health')->assertSuccessful();

        $this->assertSame(0, AdminAlert::query()->where('type', 'queue_worker_down')->count());
        $this->assertFalse(Cache::has(AlertsSystemHealthCommand::CACHE_KEY_QUEUE_STALE_FIRST_SEEN));
    }

    public function test_fake_drivers_with_assume_production_creates_system_config_alert(): void
    {
        $this->bindMegaDiagnoseRun($this->megaSkip());

        config([
            'payment.provider' => 'fake',
            'services.bank.driver' => 'bankart',
            'services.fiscalization.driver' => 'real',
            'payment.fake_e2e_sync' => false,
        ]);

        $this->artisan('alerts:system-health', ['--assume-production' => true])->assertSuccessful();
        $this->assertSame(0, AdminAlert::query()->where('type', 'system_config_fake_production')->count());

        config(['services.bank.driver' => 'fake']);

        $this->artisan('alerts:system-health', ['--assume-production' => true])->assertSuccessful();

        $this->assertDatabaseHas('admin_alerts', [
            'type' => 'system_config_fake_production',
        ]);
    }

    public function test_failed_external_file_archives_create_daily_rollup(): void
    {
        $this->bindMegaDiagnoseRun($this->megaSkip());

        ExternalFileArchive::query()->create([
            'source_table' => 't',
            'source_id' => 1,
            'source_column' => 'c',
            'context_type' => 'x',
            'archive_provider' => ExternalFileArchive::PROVIDER_MEGA,
            'generated_file_name' => 'g__t_1__c__'.Str::uuid()->toString().'.pdf',
            'original_local_path' => 'p/x.pdf',
            'status' => ExternalFileArchive::STATUS_FAILED,
            'error_message' => 'x',
        ]);

        $this->artisan('alerts:system-health')->assertSuccessful();

        $this->assertDatabaseHas('admin_alerts', [
            'type' => 'system_health_daily',
        ]);
        $row = AdminAlert::query()->where('type', 'system_health_daily')->first();
        $this->assertStringContainsString('Neuspjela arhiva', $row->message);
    }

    public function test_mega_diagnose_failure_in_rollup_when_configured(): void
    {
        config([
            'services.mega.email' => 'ops@test.local',
            'services.mega.password' => 'secret',
        ]);

        $this->bindMegaDiagnoseRun([
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

        $this->artisan('alerts:system-health')->assertSuccessful();

        $this->assertDatabaseHas('admin_alerts', [
            'type' => 'system_health_daily',
        ]);
        $row = AdminAlert::query()->where('type', 'system_health_daily')->first();
        $this->assertStringContainsString('MEGA: problem', $row->message);
    }

    public function test_clean_system_does_not_create_daily_rollup(): void
    {
        config([
            'services.mega.email' => '',
            'services.mega.password' => '',
        ]);

        $this->bindMegaDiagnoseRun($this->megaSkip());

        $this->artisan('alerts:system-health')->assertSuccessful();

        $this->assertSame(0, AdminAlert::query()->where('type', 'system_health_daily')->count());
    }

    public function test_unresolved_post_fiscalization_over_2h_in_rollup(): void
    {
        $this->bindMegaDiagnoseRun($this->megaSkip());

        $drop = ListOfTimeSlot::query()->create(['time_slot' => '08:00 - 08:20']);
        $pick = ListOfTimeSlot::query()->create(['time_slot' => '09:00 - 09:20']);
        $vt = VehicleType::query()->create(['price' => 5]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'Car',
            'description' => null,
        ]);

        $r = Reservation::query()->create([
            'merchant_transaction_id' => (string) Str::uuid(),
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'reservation_date' => now()->addDay()->toDateString(),
            'user_name' => 'T',
            'country' => 'ME',
            'license_plate' => 'AB123',
            'vehicle_type_id' => $vt->id,
            'email' => 't@example.com',
            'status' => 'paid',
            'invoice_amount' => '5.00',
            'email_sent' => Reservation::EMAIL_NOT_SENT,
        ]);

        DB::table('post_fiscalization_data')->insert([
            'reservation_id' => $r->id,
            'merchant_transaction_id' => (string) Str::uuid(),
            'error' => 'x',
            'attempts' => 1,
            'next_retry_at' => null,
            'resolved_at' => null,
            'admin_notified_at' => null,
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);

        $this->artisan('alerts:system-health')->assertSuccessful();

        $row = AdminAlert::query()->where('type', 'system_health_daily')->first();
        $this->assertNotNull($row);
        $this->assertStringContainsString('post_fiscalization', $row->message);
    }

    public function test_operational_heartbeat_cache_after_successful_run(): void
    {
        $this->bindMegaDiagnoseRun($this->megaSkip());

        $this->artisan('alerts:system-health')->assertSuccessful();

        $this->assertNotNull(Cache::get(OperationalHeartbeatCache::SYSTEM_HEALTH_LAST_RUN_AT));
        $this->assertNotNull(Cache::get(OperationalHeartbeatCache::SYSTEM_HEALTH_LAST_OK_AT));
        $this->assertNotNull(Cache::get(OperationalHeartbeatCache::MEGA_LAST_DIAGNOSE_AT));
        $this->assertTrue(Cache::get(OperationalHeartbeatCache::MEGA_LAST_DIAGNOSE_OK));
        $this->assertFalse(Cache::has(OperationalHeartbeatCache::MEGA_LAST_DIAGNOSE_ERROR));
    }

    public function test_operational_heartbeat_stores_mega_error_when_diagnose_reports_error(): void
    {
        $this->bindMegaDiagnoseRun([
            'ok' => false,
            'email_present' => true,
            'password_present' => true,
            'base_folder' => 'bus.kotor',
            'login_ok' => false,
            'folder_found' => false,
            'node_version' => null,
            'root_children_sample' => [],
            'error' => 'MEGA login failed',
            'node_binary' => 'node',
            'user_agent' => 'x',
        ]);

        $this->artisan('alerts:system-health')->assertSuccessful();

        $this->assertFalse(Cache::get(OperationalHeartbeatCache::MEGA_LAST_DIAGNOSE_OK));
        $this->assertSame('MEGA login failed', Cache::get(OperationalHeartbeatCache::MEGA_LAST_DIAGNOSE_ERROR));
    }
}
