<?php

namespace Tests\Unit;

use App\Support\CheckoutResultFlash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckoutResultFlashReservationBannerTest extends TestCase
{
    #[Test]
    public function paid_without_fiscal_and_without_known_delay_uses_processing_keys(): void
    {
        $banner = CheckoutResultFlash::forReservationSuccess(false, false, false);
        $this->assertSame('info', $banner['level']);
        $this->assertSame('paid_processing_title', $banner['title_key']);
        $this->assertSame('paid_processing_message', $banner['message_key']);
    }

    #[Test]
    public function paid_with_fiscal_delayed_known_uses_fiscal_delayed_keys(): void
    {
        $banner = CheckoutResultFlash::forReservationSuccess(false, false, true);
        $this->assertSame('info', $banner['level']);
        $this->assertSame('fiscal_delayed_title', $banner['title_key']);
        $this->assertSame('fiscal_delayed_message', $banner['message_key']);
    }

    #[Test]
    public function paid_fiscal_complete_uses_success_paid_keys(): void
    {
        $banner = CheckoutResultFlash::forReservationSuccess(false, true, false);
        $this->assertSame('success', $banner['level']);
        $this->assertSame('paid_success_title', $banner['title_key']);
    }
}
