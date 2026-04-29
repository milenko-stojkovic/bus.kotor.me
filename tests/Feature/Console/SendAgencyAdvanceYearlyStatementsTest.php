<?php

namespace Tests\Feature\Console;

use App\Mail\AgencyAdvanceYearlyStatementMail;
use App\Models\AgencyAdvanceTransaction;
use App\Models\AgencyAdvanceYearlyStatement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class SendAgencyAdvanceYearlyStatementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_only_to_agencies_with_ledger_activity_in_previous_year_and_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::create(2027, 1, 10, 12, 0, 0));

        /** @var User $agencyA */
        $agencyA = User::factory()->create([
            'email' => 'a@example.test',
        ]);

        /** @var User $agencyB */
        $agencyB = User::factory()->create([
            'email' => 'b@example.test',
        ]);

        // A: has opening (before year) + in-year transactions
        DB::table('agency_advance_transactions')->insert([
            'agency_user_id' => $agencyA->id,
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'amount' => 40.00,
            'note' => 'opening topup',
            'created_at' => Carbon::create(2025, 12, 31, 10, 0, 0),
            'updated_at' => Carbon::create(2025, 12, 31, 10, 0, 0),
        ]);
        DB::table('agency_advance_transactions')->insert([
            'agency_user_id' => $agencyA->id,
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'amount' => 20.00,
            'note' => 'topup in year',
            'created_at' => Carbon::create(2026, 3, 1, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 3, 1, 9, 0, 0),
        ]);
        DB::table('agency_advance_transactions')->insert([
            'agency_user_id' => $agencyA->id,
            'type' => AgencyAdvanceTransaction::TYPE_USAGE,
            'amount' => -15.00,
            'note' => 'usage in year',
            'created_at' => Carbon::create(2026, 6, 1, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 6, 1, 9, 0, 0),
        ]);
        DB::table('agency_advance_transactions')->insert([
            'agency_user_id' => $agencyA->id,
            'type' => AgencyAdvanceTransaction::TYPE_CORRECTION,
            'amount' => -5.00,
            'note' => 'correction in year',
            'created_at' => Carbon::create(2026, 10, 1, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 10, 1, 9, 0, 0),
        ]);

        // B: has transactions but not in previous year → should not receive statement
        DB::table('agency_advance_transactions')->insert([
            'agency_user_id' => $agencyB->id,
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'amount' => 10.00,
            'note' => 'not in year',
            'created_at' => Carbon::create(2025, 1, 1, 9, 0, 0),
            'updated_at' => Carbon::create(2025, 1, 1, 9, 0, 0),
        ]);

        Mail::fake();

        $exit = Artisan::call('advance:send-yearly-statements');
        $this->assertSame(0, $exit);

        Mail::assertSent(AgencyAdvanceYearlyStatementMail::class, 1);
        Mail::assertSent(AgencyAdvanceYearlyStatementMail::class, function (AgencyAdvanceYearlyStatementMail $m) use ($agencyA): bool {
            return $m->hasTo('a@example.test')
                && $m->agency->id === $agencyA->id
                && $m->year === 2026
                && is_string($m->pdfBinary) && $m->pdfBinary !== '';
        });

        $this->assertDatabaseHas('agency_advance_yearly_statements', [
            'agency_user_id' => $agencyA->id,
            'year' => 2026,
        ]);

        /** @var AgencyAdvanceYearlyStatement $st */
        $st = AgencyAdvanceYearlyStatement::query()->where('agency_user_id', $agencyA->id)->where('year', 2026)->firstOrFail();
        $this->assertNotNull($st->sent_at);
        $this->assertSame('a@example.test', $st->email);

        // Second run should be idempotent for A
        Artisan::call('advance:send-yearly-statements');
        Mail::assertSent(AgencyAdvanceYearlyStatementMail::class, 1);
    }

    public function test_statement_balances_and_totals_are_calculated_correctly(): void
    {
        Carbon::setTestNow(Carbon::create(2027, 1, 10, 12, 0, 0));

        /** @var User $agency */
        $agency = User::factory()->create([
            'email' => 'a@example.test',
        ]);

        // opening: 100
        DB::table('agency_advance_transactions')->insert([
            'agency_user_id' => $agency->id,
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'amount' => 100.00,
            'note' => 'opening topup',
            'created_at' => Carbon::create(2025, 12, 20, 10, 0, 0),
            'updated_at' => Carbon::create(2025, 12, 20, 10, 0, 0),
        ]);

        // 2026: +20, -10, +5 correction
        DB::table('agency_advance_transactions')->insert([
            'agency_user_id' => $agency->id,
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'amount' => 20.00,
            'note' => 'topup',
            'created_at' => Carbon::create(2026, 1, 2, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 1, 2, 10, 0, 0),
        ]);
        DB::table('agency_advance_transactions')->insert([
            'agency_user_id' => $agency->id,
            'type' => AgencyAdvanceTransaction::TYPE_USAGE,
            'amount' => -10.00,
            'note' => 'usage',
            'created_at' => Carbon::create(2026, 1, 3, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 1, 3, 10, 0, 0),
        ]);
        DB::table('agency_advance_transactions')->insert([
            'agency_user_id' => $agency->id,
            'type' => AgencyAdvanceTransaction::TYPE_CORRECTION,
            'amount' => 5.00,
            'note' => 'correction',
            'created_at' => Carbon::create(2026, 1, 4, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 1, 4, 10, 0, 0),
        ]);

        $svc = app(\App\Services\AgencyAdvance\AgencyAdvanceYearlyStatementService::class);
        $data = $svc->buildForAgencyYear($agency, 2026);

        $this->assertSame(100.0, $data['opening_balance']);
        $this->assertCount(3, $data['rows']);

        // running balances: 120, 110, 115
        $this->assertSame(120.0, $data['rows'][0]['balance_after']);
        $this->assertSame(110.0, $data['rows'][1]['balance_after']);
        $this->assertSame(115.0, $data['rows'][2]['balance_after']);

        $this->assertSame(115.0, $data['closing_balance']);
        $this->assertSame(20.0, $data['totals']['topup_total']);
        $this->assertSame(10.0, $data['totals']['usage_total']);
        $this->assertSame(5.0, $data['totals']['correction_total']);
    }
}

