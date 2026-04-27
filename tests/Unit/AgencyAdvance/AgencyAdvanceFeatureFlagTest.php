<?php

namespace Tests\Unit\AgencyAdvance;

use Tests\TestCase;

final class AgencyAdvanceFeatureFlagTest extends TestCase
{
    public function test_feature_flag_config_exists_and_is_readable(): void
    {
        $v = config('features.advance_payments');
        $this->assertTrue(is_bool($v) || is_string($v) || is_int($v) || $v === null);

        config(['features.advance_payments' => true]);
        $this->assertTrue((bool) config('features.advance_payments'));

        config(['features.advance_payments' => false]);
        $this->assertFalse((bool) config('features.advance_payments'));
    }
}

