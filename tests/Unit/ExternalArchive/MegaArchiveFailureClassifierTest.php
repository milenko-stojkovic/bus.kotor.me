<?php

namespace Tests\Unit\ExternalArchive;

use App\Services\ExternalArchive\MegaArchiveFailureClassifier;
use PHPUnit\Framework\TestCase;

class MegaArchiveFailureClassifierTest extends TestCase
{
    private MegaArchiveFailureClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new MegaArchiveFailureClassifier;
    }

    public function test_timeout_is_transient(): void
    {
        $this->assertTrue($this->classifier->isTransient('connection timeout after 30s'));
    }

    public function test_econnreset_is_transient(): void
    {
        $this->assertTrue($this->classifier->isTransient('read ECONNRESET'));
    }

    public function test_eai_again_is_transient(): void
    {
        $this->assertTrue($this->classifier->isTransient('getaddrinfo EAI_AGAIN'));
    }

    public function test_wrong_password_is_not_transient(): void
    {
        $this->assertFalse($this->classifier->isTransient('Wrong password for MEGA account'));
    }

    public function test_folder_missing_is_not_transient(): void
    {
        $this->assertFalse($this->classifier->isTransient('folder not found: bus.kotor'));
    }

    public function test_local_file_missing_message_not_transient(): void
    {
        $this->assertFalse($this->classifier->isTransient('Local file does not exist on private disk: x/y'));
    }

    public function test_short_reason_truncates_whitespace(): void
    {
        $long = str_repeat('abc ', 100);
        $s = $this->classifier->shortReason($long);
        $this->assertLessThanOrEqual(200, mb_strlen($s));
    }
}
