<?php

namespace Tests\Feature\Panel;

use App\Models\AgencyAdvanceTransaction;
use App\Models\LimoQrToken;
use App\Models\User;
use App\Services\Limo\LimoQrService;
use App\Services\Pdf\LimoQrPdfGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LimoQrPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-10 12:00:00', 'Europe/Podgorica'));
        config(['features.advance_payments' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_owner_can_download_pdf_and_generator_receives_raw_payload(): void
    {
        $user = User::factory()->create(['lang' => 'cg']);
        $this->seedAdvance($user->id);

        $svc = app(LimoQrService::class);
        $token = $svc->generateForAgency($user->id)['token'];
        $expectedRaw = $svc->decryptRawPayload($token);

        $capturedRaw = null;
        $capturedTokenId = null;
        $capturedUserId = null;
        $this->app->bind(LimoQrPdfGenerator::class, function () use (&$capturedRaw, &$capturedTokenId, &$capturedUserId) {
            return new class($capturedRaw, $capturedTokenId, $capturedUserId) extends LimoQrPdfGenerator {
                public function __construct(private mixed &$capturedRaw, private mixed &$capturedTokenId, private mixed &$capturedUserId) {}

                public function renderBinary(LimoQrToken $token, string $raw, User $user): string
                {
                    $this->capturedRaw = $raw;
                    $this->capturedTokenId = $token->id;
                    $this->capturedUserId = $user->id;

                    return '%PDF-stub';
                }
            };
        });

        $resp = $this->actingAs($user)
            ->get(route('panel.limo.qr.pdf', ['limoQrToken' => $token->id], false));

        $resp->assertOk();
        $this->assertSame('application/pdf', $resp->headers->get('Content-Type'));

        $this->assertSame($expectedRaw, $capturedRaw);
        $this->assertSame($token->id, $capturedTokenId);
        $this->assertSame($user->id, $capturedUserId);
    }

    public function test_other_user_cannot_access_pdf_404(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->seedAdvance($owner->id);

        $token = app(LimoQrService::class)->generateForAgency($owner->id)['token'];

        $this->actingAs($other)
            ->get(route('panel.limo.qr.pdf', ['limoQrToken' => $token->id], false))
            ->assertNotFound();
    }

    public function test_token_not_valid_today_returns_404(): void
    {
        $user = User::factory()->create();
        $this->seedAdvance($user->id);

        $yesterday = Carbon::today('Europe/Podgorica')->subDay();

        $token = LimoQrToken::query()->create([
            'agency_user_id' => $user->id,
            'token_hash' => 'hash',
            'encrypted_token' => encrypt('raw'),
            'valid_on' => $yesterday,
        ]);

        $this->actingAs($user)
            ->get(route('panel.limo.qr.pdf', ['limoQrToken' => $token->id], false))
            ->assertNotFound();
    }

    public function test_response_is_pdf_when_using_real_generator(): void
    {
        $user = User::factory()->create(['lang' => 'en']);
        $this->seedAdvance($user->id);

        $token = app(LimoQrService::class)->generateForAgency($user->id)['token'];

        $resp = $this->actingAs($user)
            ->get(route('panel.limo.qr.pdf', ['limoQrToken' => $token->id], false));

        $resp->assertOk();
        $this->assertSame('application/pdf', $resp->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $resp->streamedContent());
    }

    private function seedAdvance(int $agencyUserId): void
    {
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agencyUserId,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'test',
        ]);
    }
}

