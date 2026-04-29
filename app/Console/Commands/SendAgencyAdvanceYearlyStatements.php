<?php

namespace App\Console\Commands;

use App\Models\AgencyAdvanceTransaction;
use App\Models\User;
use App\Services\AgencyAdvance\AgencyAdvanceYearlyStatementService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class SendAgencyAdvanceYearlyStatements extends Command
{
    protected $signature = 'advance:send-yearly-statements';

    protected $description = 'Send yearly advance statements (previous year) to agencies that had ledger activity';

    public function handle(AgencyAdvanceYearlyStatementService $svc): int
    {
        $year = (int) Carbon::now()->subYear()->format('Y');
        $start = Carbon::create($year, 1, 1, 0, 0, 0)->startOfDay();
        $end = Carbon::create($year, 12, 31, 23, 59, 59)->endOfDay();

        $agencyIds = AgencyAdvanceTransaction::query()
            ->where('created_at', '>=', $start->toDateTimeString())
            ->where('created_at', '<=', $end->toDateTimeString())
            ->select('agency_user_id')
            ->distinct()
            ->pluck('agency_user_id')
            ->map(fn ($v) => (int) $v)
            ->values();

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($agencyIds as $agencyUserId) {
            /** @var User|null $agency */
            $agency = User::query()->whereKey($agencyUserId)->first();
            if (! $agency) {
                continue;
            }

            $res = $svc->sendForAgencyYear($agency, $year);
            if ($res === 'sent') {
                $sent++;
            } elseif ($res === 'skipped') {
                $skipped++;
            } else {
                $failed++;
            }
        }

        Log::channel('payments')->info('advance_yearly_statements_command_done', [
            'year' => $year,
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
            'agencies_total' => $agencyIds->count(),
        ]);

        $this->info("Year {$year}: sent={$sent}, skipped={$skipped}, failed={$failed}, agencies={$agencyIds->count()}");

        return self::SUCCESS;
    }
}

