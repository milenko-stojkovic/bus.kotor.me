<?php

namespace App\Services\Limo;

use App\Models\LimoPickupEvent;
use App\Models\LimoQrToken;
use App\Models\User;
use App\Services\AgencyAdvance\AgencyAdvanceService;
use Carbon\Carbon;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class LimoQrService
{
    public const MAX_ACTIVE_GENERATIONS_PER_DAY = 20;

    public function __construct(
        private readonly AgencyAdvanceService $agencyAdvanceService,
    ) {}

    /**
     * Aktivni QR redovi za danas (iskorišćeni su uklonjeni iz tabele pri pickup-u).
     *
     * @return Collection<int, LimoQrToken>
     */
    public function listActiveTokensForToday(int $agencyUserId): Collection
    {
        $today = Carbon::today('Europe/Podgorica');

        return LimoQrToken::query()
            ->where('agency_user_id', $agencyUserId)
            ->whereDate('valid_on', $today->toDateString())
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Ukupno „potrošenih” QR slotova za danas: aktivni tokeni + realizovani QR pickup-i (token obrisan poslije pickup-a).
     */
    public function countQrSlotsUsedToday(int $agencyUserId): int
    {
        $today = Carbon::today('Europe/Podgorica');
        $dateString = $today->toDateString();

        $activeTokens = LimoQrToken::query()
            ->where('agency_user_id', $agencyUserId)
            ->whereDate('valid_on', $dateString)
            ->count();

        $consumedToday = LimoPickupEvent::query()
            ->where('agency_user_id', $agencyUserId)
            ->where('source', 'qr')
            ->whereNotNull('qr_token_hash')
            ->whereDate('qr_valid_on', $dateString)
            ->count();

        return $activeTokens + $consumedToday;
    }

    /**
     * @return array{token: LimoQrToken, raw_token: string}
     *
     * @throws \RuntimeException
     */
    public function generateForAgency(int $agencyUserId): array
    {
        if (! $this->agencyAdvanceService->canSpend($agencyUserId, LimoPickupService::AMOUNT_EUR)) {
            Log::channel('payments')->warning('limo_qr_generation_failed_insufficient_advance', [
                'agency_user_id' => $agencyUserId,
            ]);
            throw new \RuntimeException('insufficient_advance');
        }

        $today = Carbon::today('Europe/Podgorica');

        return DB::transaction(function () use ($agencyUserId, $today) {
            User::query()->whereKey($agencyUserId)->lockForUpdate()->first();

            if ($this->countQrSlotsUsedToday($agencyUserId) >= self::MAX_ACTIVE_GENERATIONS_PER_DAY) {
                Log::channel('payments')->warning('limo_qr_generation_failed_limit', [
                    'agency_user_id' => $agencyUserId,
                ]);
                throw new \RuntimeException('limit');
            }

            $raw = bin2hex(random_bytes(32));
            $hash = LimoPickupService::hashToken($raw);
            $encrypted = Crypt::encryptString($raw);

            /** @var LimoQrToken $row */
            $row = LimoQrToken::query()->create([
                'agency_user_id' => $agencyUserId,
                'token_hash' => $hash,
                'encrypted_token' => $encrypted,
                'valid_on' => $today,
            ]);

            Log::channel('payments')->info('limo_qr_generated', [
                'agency_user_id' => $agencyUserId,
                'limo_qr_token_id' => $row->id,
                'token_hash' => $hash,
            ]);

            return ['token' => $row, 'raw_token' => $raw];
        });
    }

    /**
     * Dekriptuje reprezentaciju za QR (isti oblik kao na skeniranju).
     *
     * @throws \Illuminate\Contracts\Encryption\DecryptException
     */
    public function decryptRawPayload(LimoQrToken $token): string
    {
        if ($token->encrypted_token === null || $token->encrypted_token === '') {
            throw new \RuntimeException('missing_encrypted_token');
        }

        return Crypt::decryptString($token->encrypted_token);
    }

    public static function qrImageDataUri(string $rawPayload): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($rawPayload)
            ->size(280)
            ->margin(10)
            ->build();

        return 'data:'.$result->getMimeType().';base64,'.base64_encode($result->getString());
    }
}
