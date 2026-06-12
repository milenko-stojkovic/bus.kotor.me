<?php

namespace Tests;

use App\Services\Reservation\ReservationVehicleEligibilityService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        ReservationVehicleEligibilityService::clearCache();
        parent::tearDown();
    }
}
