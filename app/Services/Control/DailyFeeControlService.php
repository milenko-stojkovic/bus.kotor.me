<?php

namespace App\Services\Control;

use App\Models\Reservation;
use App\Services\Reservation\DuplicateReservationAttemptService;
use App\Support\ReservationKind;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class DailyFeeControlService
{
    public function normalizePlate(?string $licensePlate): string
    {
        return DuplicateReservationAttemptService::normalizeLicensePlate($licensePlate);
    }

    /**
     * @return array{
     *     found: bool,
     *     normalized_plate: string,
     *     validity_date: string,
     *     validity_date_display: string,
     *     matches: list<array{
     *         id: int,
     *         license_plate: string,
     *         user_name: string,
     *         email: string,
     *         reservation_date: string,
     *         vehicle_type_label: string,
     *         created_at: string
     *     }>
     * }
     */
    public function checkPlateForToday(string $licensePlate): array
    {
        $normalized = $this->normalizePlate($licensePlate);
        $today = Carbon::today('Europe/Podgorica');

        /** @var Collection<int, Reservation> $rows */
        $rows = Reservation::query()
            ->where('reservation_kind', ReservationKind::DAILY_TICKET)
            ->whereDate('reservation_date', $today)
            ->where('status', 'paid')
            ->where('license_plate', $normalized)
            ->with(['vehicleType.translations'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return [
            'found' => $rows->isNotEmpty(),
            'normalized_plate' => $normalized,
            'validity_date' => $today->toDateString(),
            'validity_date_display' => $today->format('d.m.Y'),
            'matches' => $rows->map(fn (Reservation $r) => $this->formatMatch($r))->values()->all(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     license_plate: string,
     *     user_name: string,
     *     email: string,
     *     reservation_date: string,
     *     vehicle_type_label: string,
     *     created_at: string
     * }
     */
    private function formatMatch(Reservation $reservation): array
    {
        $vehicleType = $reservation->vehicleType;
        $vehicleLabel = '—';
        if ($vehicleType !== null) {
            $vehicleLabel = $vehicleType->getTranslatedName('cg')
                ?: $vehicleType->getTranslatedDescription('cg')
                ?: '—';
        }

        $createdAt = $reservation->created_at
            ? Carbon::parse($reservation->created_at)->timezone('Europe/Podgorica')->format('d.m.Y H:i')
            : '—';

        return [
            'id' => (int) $reservation->id,
            'license_plate' => (string) $reservation->license_plate,
            'user_name' => (string) $reservation->user_name,
            'email' => (string) $reservation->email,
            'reservation_date' => $reservation->reservation_date
                ? $reservation->reservation_date->format('d.m.Y')
                : '—',
            'vehicle_type_label' => $vehicleLabel,
            'created_at' => $createdAt,
        ];
    }
}
