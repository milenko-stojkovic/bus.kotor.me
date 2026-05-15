<?php

namespace Tests\Unit\AdminPanel;

use App\Models\AdminAlert;
use App\Services\AdminPanel\AdminAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_once_does_not_duplicate_same_type_and_dedupe_key(): void
    {
        $svc = new AdminAlertService;

        $first = $svc->createOnce(
            'test_alert',
            'T1',
            'M1',
            'medium',
            'key-a',
            ['foo' => 1],
        );
        $second = $svc->createOnce(
            'test_alert',
            'T2',
            'M2',
            'high',
            'key-a',
            ['foo' => 2],
        );

        $this->assertInstanceOf(AdminAlert::class, $first);
        $this->assertNull($second);
        $this->assertSame(1, AdminAlert::query()->where('type', 'test_alert')->count());
        $this->assertSame('key-a', $first->fresh()->payload_json['dedupe_key'] ?? null);
    }

    public function test_create_once_without_dedupe_creates_each_time(): void
    {
        $svc = new AdminAlertService;
        $a = $svc->createOnce('t', 'x', 'y', null, null, []);
        $b = $svc->createOnce('t', 'x', 'y', null, null, []);

        $this->assertInstanceOf(AdminAlert::class, $a);
        $this->assertInstanceOf(AdminAlert::class, $b);
        $this->assertSame(2, AdminAlert::query()->where('type', 't')->count());
    }
}
