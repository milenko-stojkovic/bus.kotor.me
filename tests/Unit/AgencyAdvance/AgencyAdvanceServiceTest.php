<?php

namespace Tests\Unit\AgencyAdvance;

use App\Models\AgencyAdvanceTopup;
use App\Models\AgencyAdvanceTransaction;
use App\Models\User;
use App\Services\AgencyAdvance\AgencyAdvanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AgencyAdvanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_can_be_created(): void
    {
        $user = User::factory()->create();

        $topup = AgencyAdvanceTopup::query()->create([
            'agency_user_id' => $user->id,
            'merchant_transaction_id' => 'mtid_1',
            'amount' => '100.00',
            'status' => AgencyAdvanceTopup::STATUS_PENDING,
        ]);
        $this->assertNotNull($topup->id);

        $tx = AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'merchant_transaction_id' => 'mtid_1',
        ]);
        $this->assertNotNull($tx->id);
    }

    public function test_balance_is_sum_of_ledger_amounts(): void
    {
        $user = User::factory()->create();

        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
        ]);
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '-30.00',
            'type' => AgencyAdvanceTransaction::TYPE_USAGE,
        ]);
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '-5.00',
            'type' => AgencyAdvanceTransaction::TYPE_CORRECTION,
        ]);

        $svc = new AgencyAdvanceService();
        $this->assertSame('65.00', $svc->balance($user->id));
    }

    public function test_is_valid_topup_amount_rules(): void
    {
        $svc = new AgencyAdvanceService();

        // allowed
        $this->assertTrue($svc->isValidTopupAmount(15));
        $this->assertTrue($svc->isValidTopupAmount('15.00'));
        $this->assertTrue($svc->isValidTopupAmount(100));
        $this->assertTrue($svc->isValidTopupAmount('105.00'));

        // not allowed
        $this->assertFalse($svc->isValidTopupAmount('100.50'));
        $this->assertFalse($svc->isValidTopupAmount(102));
        $this->assertFalse($svc->isValidTopupAmount('12.34'));
        $this->assertFalse($svc->isValidTopupAmount(0));
        $this->assertFalse($svc->isValidTopupAmount(-15));
        $this->assertFalse($svc->isValidTopupAmount('-15.00'));

        // explicitly: whole euros but not ending in 0/5
        $this->assertFalse($svc->isValidTopupAmount('102.00'));
    }

    public function test_can_spend_compares_to_balance(): void
    {
        $user = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $user->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
        ]);

        $svc = new AgencyAdvanceService();
        $this->assertTrue($svc->canSpend($user->id, 80));
        $this->assertFalse($svc->canSpend($user->id, 120));
    }
}

