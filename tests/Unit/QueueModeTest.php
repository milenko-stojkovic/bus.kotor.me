<?php

namespace Tests\Unit;

use App\Support\QueueMode;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueueModeTest extends TestCase
{
    #[Test]
    public function use_sync_for_fake_requires_both_drivers_fake_and_flag(): void
    {
        config(['payment.fake_e2e_sync' => true]);
        config(['services.bank.driver' => 'fake']);
        config(['services.fiscalization.driver' => 'fake']);
        $this->assertTrue(QueueMode::useSyncForFake());

        config(['services.bank.driver' => 'bankart']);
        $this->assertFalse(QueueMode::useSyncForFake());

        config(['services.bank.driver' => 'fake']);
        config(['services.fiscalization.driver' => 'real']);
        $this->assertFalse(QueueMode::useSyncForFake());

        config(['services.fiscalization.driver' => 'fake']);
        config(['payment.fake_e2e_sync' => false]);
        $this->assertFalse(QueueMode::useSyncForFake());
    }
}
