<?php

namespace Tests\Unit;

use App\Services\Payment\ErrorClassifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ErrorClassifierBankartAmountTest extends TestCase
{
    #[Test]
    public function lesser_amount_message_maps_to_bank_invalid_amount(): void
    {
        $c = new ErrorClassifier;
        $r = $c->classify('bankart', 2003, 'Enter lesser amount', null, ['stage' => 'create_session']);

        $this->assertSame('bank_invalid_amount', $r['resolution_reason']);
        $this->assertSame('payment_processing_issue', $r['user_message_key']);
        $this->assertTrue($r['notify_admin']);
    }

    #[Test]
    public function code_2003_without_amount_phrase_stays_authorization_declined(): void
    {
        $c = new ErrorClassifier;
        $r = $c->classify('bankart', 2003, 'Do not honor', null, ['stage' => 'payment_callback']);

        $this->assertSame('authorization_declined', $r['resolution_reason']);
        $this->assertFalse($r['notify_admin']);
    }
}
