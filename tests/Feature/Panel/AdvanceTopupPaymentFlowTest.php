<?php

namespace Tests\Feature\Panel;

use App\Jobs\PaymentCallbackJob;
use App\Mail\AdvanceTopupConfirmationMail;
use App\Models\AgencyAdvanceTopup;
use App\Models\AgencyAdvanceTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdvanceTopupPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_bank_driver_marks_topup_paid_and_creates_ledger_and_increases_balance_without_temp_data(): void
    {
        config(['features.advance_payments' => true]);
        config(['services.bank.driver' => 'fake']);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        Mail::fake();

        $this->post(route('panel.advance.topup.store', [], false), ['amount' => '100'])
            ->assertRedirect(route('panel.advance.index', [], false));

        $topup = AgencyAdvanceTopup::query()->firstOrFail();
        $this->assertSame(AgencyAdvanceTopup::STATUS_PAID, (string) $topup->status);
        $this->assertNotNull($topup->confirmation_sent_at);
        $this->assertSame($user->email, (string) $topup->confirmation_email);

        $ledger = AgencyAdvanceTransaction::query()->firstOrFail();
        $this->assertSame($user->id, (int) $ledger->agency_user_id);
        $this->assertSame(AgencyAdvanceTransaction::TYPE_TOPUP, (string) $ledger->type);
        $this->assertSame('advance_topup', (string) $ledger->reference_type);
        $this->assertSame($topup->id, (int) $ledger->reference_id);
        $this->assertSame((string) $topup->amount, (string) $ledger->amount);

        Mail::assertSent(AdvanceTopupConfirmationMail::class, 1);
        Mail::assertSent(AdvanceTopupConfirmationMail::class, function (AdvanceTopupConfirmationMail $m) use ($user): bool {
            return $m->hasTo($user->email) && is_string($m->pdfBinary) && $m->pdfBinary !== '';
        });

        // Ensure we did not touch temp_data
        $this->assertFalse(DB::table('temp_data')->where('merchant_transaction_id', $topup->merchant_transaction_id)->exists());
    }

    public function test_create_session_failure_marks_topup_failed_and_does_not_create_ledger(): void
    {
        config(['features.advance_payments' => true]);
        config(['services.bank.driver' => 'bankart']);
        // Missing bankart config in tests -> createSession should fail as unavailable.
        config(['services.bankart.api_url' => null, 'services.bankart.api_key' => null, 'services.bankart.username' => null, 'services.bankart.password' => null]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $this->post(route('panel.advance.topup.store', [], false), ['amount' => '100'])
            ->assertRedirect(route('panel.advance.index', [], false));

        $topup = AgencyAdvanceTopup::query()->firstOrFail();
        $this->assertSame(AgencyAdvanceTopup::STATUS_FAILED, (string) $topup->status);
        $this->assertSame(0, AgencyAdvanceTransaction::query()->count());
    }

    public function test_success_callback_is_idempotent_and_creates_ledger_once(): void
    {
        config(['features.advance_payments' => true]);

        Mail::fake();

        $user = User::factory()->create();
        $mtid = (string) Str::uuid();
        $topup = AgencyAdvanceTopup::query()->create([
            'agency_user_id' => $user->id,
            'merchant_transaction_id' => $mtid,
            'amount' => '105.00',
            'status' => AgencyAdvanceTopup::STATUS_PENDING,
        ]);

        $job1 = new PaymentCallbackJob(['merchant_transaction_id' => $mtid, 'status' => 'success'], ['result' => 'OK']);
        $job1->handle();

        $job2 = new PaymentCallbackJob(['merchant_transaction_id' => $mtid, 'status' => 'success'], ['result' => 'OK']);
        $job2->handle();

        $topup->refresh();
        $this->assertSame(AgencyAdvanceTopup::STATUS_PAID, (string) $topup->status);
        $this->assertSame(1, AgencyAdvanceTransaction::query()->where('reference_id', $topup->id)->count());

        Mail::assertSent(AdvanceTopupConfirmationMail::class, 1);
    }

    public function test_failed_callback_marks_failed_and_does_not_create_ledger(): void
    {
        config(['features.advance_payments' => true]);

        Mail::fake();

        $user = User::factory()->create();
        $mtid = (string) Str::uuid();
        AgencyAdvanceTopup::query()->create([
            'agency_user_id' => $user->id,
            'merchant_transaction_id' => $mtid,
            'amount' => '100.00',
            'status' => AgencyAdvanceTopup::STATUS_PENDING,
        ]);

        $job = new PaymentCallbackJob(['merchant_transaction_id' => $mtid, 'status' => 'failed'], ['result' => 'ERROR']);
        $job->handle();

        $topup = AgencyAdvanceTopup::query()->firstOrFail();
        $this->assertSame(AgencyAdvanceTopup::STATUS_FAILED, (string) $topup->status);
        $this->assertSame(0, AgencyAdvanceTransaction::query()->count());

        Mail::assertNothingSent();
    }

    public function test_failed_callback_after_paid_does_not_downgrade_or_change_ledger(): void
    {
        config(['features.advance_payments' => true]);

        $user = User::factory()->create();
        $mtid = (string) Str::uuid();
        $topup = AgencyAdvanceTopup::query()->create([
            'agency_user_id' => $user->id,
            'merchant_transaction_id' => $mtid,
            'amount' => '100.00',
            'status' => AgencyAdvanceTopup::STATUS_PENDING,
        ]);

        (new PaymentCallbackJob(['merchant_transaction_id' => $mtid, 'status' => 'success'], ['result' => 'OK']))->handle();
        $this->assertSame(1, AgencyAdvanceTransaction::query()->count());

        (new PaymentCallbackJob(['merchant_transaction_id' => $mtid, 'status' => 'failed'], ['result' => 'ERROR']))->handle();

        $topup->refresh();
        $this->assertSame(AgencyAdvanceTopup::STATUS_PAID, (string) $topup->status);
        $this->assertSame(1, AgencyAdvanceTransaction::query()->count());
    }

    public function test_feature_flag_false_prevents_callback_processing_for_topups(): void
    {
        config(['features.advance_payments' => false]);

        $user = User::factory()->create();
        $mtid = (string) Str::uuid();
        $topup = AgencyAdvanceTopup::query()->create([
            'agency_user_id' => $user->id,
            'merchant_transaction_id' => $mtid,
            'amount' => '100.00',
            'status' => AgencyAdvanceTopup::STATUS_PENDING,
        ]);

        (new PaymentCallbackJob(['merchant_transaction_id' => $mtid, 'status' => 'success'], ['result' => 'OK']))->handle();

        $topup->refresh();
        $this->assertSame(AgencyAdvanceTopup::STATUS_PENDING, (string) $topup->status);
        $this->assertSame(0, AgencyAdvanceTransaction::query()->count());
    }

    public function test_mail_failure_does_not_rollback_paid_or_ledger_and_keeps_confirmation_unsent(): void
    {
        config(['features.advance_payments' => true]);
        config(['services.bank.driver' => 'fake']);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $mailer = \Mockery::mock(\Illuminate\Contracts\Mail\Mailer::class);
        $mailer->shouldReceive('to')->andReturnSelf();
        $mailer->shouldReceive('send')->andThrow(new \RuntimeException('mail fail'));
        $this->app->instance(\Illuminate\Contracts\Mail\Mailer::class, $mailer);

        $this->post(route('panel.advance.topup.store', [], false), ['amount' => '100'])
            ->assertRedirect(route('panel.advance.index', [], false));

        $topup = AgencyAdvanceTopup::query()->firstOrFail();
        $this->assertSame(AgencyAdvanceTopup::STATUS_PAID, (string) $topup->status);
        $this->assertSame(1, AgencyAdvanceTransaction::query()->count());

        $this->assertNull($topup->confirmation_sent_at);
        $this->assertNull($topup->confirmation_email);
    }
}

