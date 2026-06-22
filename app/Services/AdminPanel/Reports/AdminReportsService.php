<?php

namespace App\Services\AdminPanel\Reports;

use Carbon\Carbon;
use App\Models\Reservation;
use App\Models\VehicleType;
use App\Services\Reservation\PanelReservationListService;
use App\Services\Reservation\ReservationVehicleEligibilityService;
use App\Support\ReservationKind;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AdminReportsService
{
    public function __construct(
        private ReservationVehicleEligibilityService $vehicleEligibility,
    ) {}

    /**
     * @return array{revenue_eur:float,transactions:int}
     */
    public function byPayment(Carbon $from, Carbon $to): array
    {
        $rows = Reservation::query()
            ->where('status', 'paid')
            ->whereDate('created_at', '>=', $from->toDateString())
            ->whereDate('created_at', '<=', $to->toDateString())
            ->get(['id', 'invoice_amount']);

        $revenue = (float) $rows->sum(fn ($r) => (float) ($r->invoice_amount ?? 0));

        return [
            'revenue_eur' => $revenue,
            'transactions' => $rows->count(),
        ];
    }

    /**
     * @return array{revenue_eur:float,realized_count:int}
     */
    public function byRealization(Carbon $from, Carbon $to): array
    {
        $candidates = Reservation::query()
            ->with(['pickUpTimeSlot'])
            ->whereDate('reservation_date', '>=', $from->toDateString())
            ->whereDate('reservation_date', '<=', $to->toDateString())
            ->get(['id', 'status', 'invoice_amount', 'reservation_date', 'pick_up_time_slot_id']);

        $realized = $candidates->filter(fn (Reservation $r) => PanelReservationListService::isRealized($r));
        $revenue = (float) $realized
            ->where('status', 'paid')
            ->sum(fn (Reservation $r) => (float) ($r->invoice_amount ?? 0));

        return [
            'revenue_eur' => $revenue,
            'realized_count' => $realized->count(),
        ];
    }

    /**
     * @return array{rows:list<array{label:string,count:int}>,total:int}
     */
    public function byVehicleType(Carbon $from, Carbon $to): array
    {
        $candidates = Reservation::query()
            ->with(['pickUpTimeSlot', 'vehicleType.translations'])
            ->whereDate('reservation_date', '>=', $from->toDateString())
            ->whereDate('reservation_date', '<=', $to->toDateString())
            ->get(['id', 'vehicle_type_id', 'reservation_date', 'pick_up_time_slot_id']);

        $realized = $candidates->filter(fn (Reservation $r) => PanelReservationListService::isRealized($r));
        $counts = $realized->groupBy('vehicle_type_id')->map->count();

        $types = VehicleType::query()
            ->with(['translations'])
            ->orderBy('id')
            ->get();

        $labels = $this->vehicleTypeLabelsCg($types);

        $out = [];
        $total = 0;
        foreach ($labels as $vtId => $label) {
            $cnt = (int) ($counts->get($vtId, 0));
            $out[] = ['label' => $label, 'count' => $cnt];
            $total += $cnt;
        }

        return [
            'rows' => $out,
            'total' => $total,
        ];
    }

    /**
     * Paid reservations grouped by reservation kind; daily_ticket split into Limo vs Autobusi.
     * Date range: reservations.reservation_date. Broj vozila = broj rezervacija (redova).
     *
     * Limo: daily_ticket + vehicle_type_id in {@see ReservationVehicleEligibilityService::controlDailyFeeListVehicleTypeIds()}.
     *
     * @return array{
     *   rows: list<array{label:string,count:int,revenue_eur:float,is_subtotal?:bool,is_total?:bool}>,
     *   total_count: int,
     *   total_revenue_eur: float
     * }
     */
    public function byReservationType(Carbon $from, Carbon $to): array
    {
        $limoTypeIds = $this->vehicleEligibility->controlDailyFeeListVehicleTypeIds();

        $reservations = Reservation::query()
            ->where('status', 'paid')
            ->whereDate('reservation_date', '>=', $from->toDateString())
            ->whereDate('reservation_date', '<=', $to->toDateString())
            ->get(['id', 'reservation_kind', 'vehicle_type_id', 'invoice_amount']);

        $timeSlots = ['count' => 0, 'revenue' => 0.0];
        $limo = ['count' => 0, 'revenue' => 0.0];
        $buses = ['count' => 0, 'revenue' => 0.0];

        foreach ($reservations as $reservation) {
            $amount = (float) ($reservation->invoice_amount ?? 0);
            $kind = $reservation->reservation_kind ?? ReservationKind::TIME_SLOTS;

            if ($kind === ReservationKind::TIME_SLOTS) {
                $timeSlots['count']++;
                $timeSlots['revenue'] += $amount;

                continue;
            }

            if ($kind !== ReservationKind::DAILY_TICKET) {
                continue;
            }

            if (in_array((int) $reservation->vehicle_type_id, $limoTypeIds, true)) {
                $limo['count']++;
                $limo['revenue'] += $amount;
            } else {
                $buses['count']++;
                $buses['revenue'] += $amount;
            }
        }

        $dailyCount = $limo['count'] + $buses['count'];
        $dailyRevenue = $limo['revenue'] + $buses['revenue'];
        $totalCount = $timeSlots['count'] + $dailyCount;
        $totalRevenue = $timeSlots['revenue'] + $dailyRevenue;

        return [
            'rows' => [
                ['label' => 'Termini', 'count' => $timeSlots['count'], 'revenue_eur' => $timeSlots['revenue']],
                ['label' => 'Dnevna naknada — Limo', 'count' => $limo['count'], 'revenue_eur' => $limo['revenue']],
                ['label' => 'Dnevna naknada — Autobusi', 'count' => $buses['count'], 'revenue_eur' => $buses['revenue']],
                ['label' => 'Dnevna naknada ukupno', 'count' => $dailyCount, 'revenue_eur' => $dailyRevenue, 'is_subtotal' => true],
                ['label' => 'Ukupno', 'count' => $totalCount, 'revenue_eur' => $totalRevenue, 'is_total' => true],
            ],
            'total_count' => $totalCount,
            'total_revenue_eur' => $totalRevenue,
        ];
    }

    /**
     * Snapshot of advance obligations "as of" a given timestamp (end-of-day).
     * Ledger (agency_advance_transactions) is the source of truth.
     *
     * @return array{rows:list<array{agency_user_id:int,agency:string,email:string,topup_total:float,usage_total:float,correction_total:float,balance:float}>,total_obligations_eur:float,as_of:string}
     */
    public function advanceObligationsSnapshot(Carbon $asOf): array
    {
        $rows = DB::table('agency_advance_transactions as aat')
            ->join('users', 'users.id', '=', 'aat.agency_user_id')
            ->where('aat.created_at', '<=', $asOf->toDateTimeString())
            ->select([
                'aat.agency_user_id',
                'users.name as agency_name',
                'users.email as agency_email',
                DB::raw("COALESCE(SUM(CASE WHEN aat.type = 'topup' THEN aat.amount ELSE 0 END), 0) as topup_total"),
                // usage is negative; return as positive total
                DB::raw("COALESCE(-1 * SUM(CASE WHEN aat.type = 'usage' THEN aat.amount ELSE 0 END), 0) as usage_total"),
                DB::raw("COALESCE(SUM(CASE WHEN aat.type = 'correction' THEN aat.amount ELSE 0 END), 0) as correction_total"),
                DB::raw('COALESCE(SUM(aat.amount), 0) as balance'),
            ])
            ->groupBy('aat.agency_user_id', 'users.name', 'users.email')
            ->orderByDesc(DB::raw('COALESCE(SUM(aat.amount), 0)'))
            ->get();

        $out = [];
        $total = 0.0;
        foreach ($rows as $r) {
            $bal = (float) $r->balance;
            $out[] = [
                'agency_user_id' => (int) $r->agency_user_id,
                'agency' => (string) $r->agency_name,
                'email' => (string) $r->agency_email,
                'topup_total' => (float) $r->topup_total,
                'usage_total' => (float) $r->usage_total,
                'correction_total' => (float) $r->correction_total,
                'balance' => $bal,
            ];
            $total += $bal;
        }

        return [
            'rows' => $out,
            'total_obligations_eur' => $total,
            'as_of' => $asOf->toDateTimeString(),
        ];
    }

    /**
     * @param  Collection<int, VehicleType>  $types
     * @return array<int, string> map vehicle_type_id => label
     */
    private function vehicleTypeLabelsCg(Collection $types): array
    {
        $locale = 'cg';

        $fallback = [
            1 => 'Putničko vozilo (Putničko vozilo (4+1, 5+1, 6+1 i 7+1 mjesta))',
            2 => 'Mini bus (Mini bus (8+1 mjesta))',
            3 => 'Srednji autobus (Autobus (9–23 mjesta))',
            4 => 'Veliki autobus (Autobus (više od 23 mjesta))',
        ];

        $labels = [];
        foreach ($types->take(4) as $t) {
            $name = $t->getTranslatedName($locale) ?: ('#'.$t->id);
            $desc = $t->getTranslatedDescription($locale);
            $labels[(int) $t->id] = $desc ? ($name.' ('.$desc.')') : $name;
        }

        // Ensure 4 fixed rows even if DB differs.
        if (count($labels) < 4) {
            foreach ($fallback as $id => $label) {
                $labels[$id] = $labels[$id] ?? $label;
            }
        }

        ksort($labels);

        return $labels;
    }
}

