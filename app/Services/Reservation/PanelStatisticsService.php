<?php

namespace App\Services\Reservation;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Osnovna statistika za agency panel (tekuci korisnik), bez posebnih tabela.
 * Realized = ista definicija kao PanelReservationListService (datum + kraj pick-up slota).
 */
final class PanelStatisticsService
{
    public function __construct(
        private PanelReservationListService $reservationLists
    ) {}

    /**
     * @return array{
     *   total_paid: float,
     *   total_paid_formatted: string,
     *   visit_count: int,
     *   vehicle_usage: Collection<int, array{license_plate: string, category_label: string, visits: int}>
     * }
     */
    public function overview(User $user): array
    {
        $realized = $this->reservationLists->realizedFor($user);
        $locale = app()->getLocale();

        $paidRealized = $realized->filter(fn (Reservation $r) => $r->status === 'paid');
        $totalPaid = (float) $paidRealized->sum(fn (Reservation $r) => (float) ($r->invoice_amount ?? 0));

        $visitCount = $realized->count();

        $vehicleUsage = $realized
            ->groupBy(fn (Reservation $r) => $r->license_plate."\0".(string) $r->vehicle_type_id)
            ->map(function (Collection $group) use ($locale): array {
                /** @var Reservation $first */
                $first = $group->first();

                return [
                    'license_plate' => $first->license_plate,
                    'category_label' => $first->vehicleType?->getTranslatedName($locale) ?? '—',
                    'visits' => $group->count(),
                ];
            })
            ->sortByDesc('visits')
            ->values();

        return [
            'total_paid' => $totalPaid,
            'total_paid_formatted' => number_format($totalPaid, 2, '.', ''),
            'visit_count' => $visitCount,
            'vehicle_usage' => $vehicleUsage,
        ];
    }
}
