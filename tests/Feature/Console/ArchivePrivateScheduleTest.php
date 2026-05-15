<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ArchivePrivateScheduleTest extends TestCase
{
    public function test_files_archive_private_is_scheduled_every_six_hours_with_limit_and_mega_health(): void
    {
        Artisan::call('schedule:list');
        $out = Artisan::output();

        $this->assertStringContainsString('files:archive-private', $out);
        $this->assertStringContainsString('--source=all', $out);
        $this->assertStringContainsString('--limit=50', $out);
        $this->assertStringContainsString('--require-mega-health', $out);
        $this->assertStringContainsString('*/6', $out);
    }
}
