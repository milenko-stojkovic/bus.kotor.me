<?php

namespace App\Services\Control;

use App\Models\Reservation;
use App\Services\Reservation\DuplicateReservationAttemptService;
use App\Services\Reservation\ReservationVehicleEligibilityService;
use App\Support\ReservationKind;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class DailyFeeControlService
{
    public function __construct(
        private readonly ReservationVehicleEligibilityService $vehicleEligibility,
    ) {}

    public function normalizePlate(?string $licensePlate): string
    {
        return DuplicateReservationAttemptService::normalizeLicensePlate($licensePlate);
    }

    /**
     * @return array{
     *     found: bool,
     *     has_daily_fee: bool,
     *     has_time_slots: bool,
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
     *         created_at: string,
     *         coverage_label: string
     *     }>
     * }
     */
    public function checkPlateForToday(string $licensePlate): array
    {
        $normalized = $this->normalizePlate($licensePlate);
        $today = Carbon::today('Europe/Podgorica');

        /** @var Collection<int, Reservation> $rows */
        $rows = Reservation::query()
            ->whereDate('reservation_date', $today)
            ->where('license_plate', $normalized)
            ->where(function ($query): void {
                $query->where(function ($daily): void {
                    $daily->where('reservation_kind', ReservationKind::DAILY_TICKET)
                        ->where('status', 'paid');
                })->orWhere(function ($slots): void {
                    $slots->whereIn('status', ['paid', 'free'])
                        ->where(function ($kind): void {
                            $kind->where('reservation_kind', ReservationKind::TIME_SLOTS)
                                ->orWhereNull('reservation_kind');
                        });
                });
            })
            ->with(['vehicleType.translations'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $matches = $rows->map(fn (Reservation $r) => $this->formatCheckRow($r))->values()->all();
        $hasDailyFee = $rows->contains(
            fn (Reservation $r): bool => $r->reservation_kind === ReservationKind::DAILY_TICKET && $r->status === 'paid'
        );
        $hasTimeSlots = $rows->contains(
            fn (Reservation $r): bool => ($r->reservation_kind === ReservationKind::TIME_SLOTS || $r->reservation_kind === null)
                && in_array($r->status, ['paid', 'free'], true)
        );

        return [
            'found' => $rows->isNotEmpty(),
            'has_daily_fee' => $hasDailyFee,
            'has_time_slots' => $hasTimeSlots,
            'normalized_plate' => $normalized,
            'validity_date' => $today->toDateString(),
            'validity_date_display' => $today->format('d.m.Y'),
            'matches' => $matches,
        ];
    }

    /**
     * Paid Daily fee reservations for today — passenger/limo 4+1–7+1 and minibus 8+1 only.
     *
     * Ordered by license_plate ASC (then id ASC).
     *
     * @return array{
     *     validity_date: string,
     *     validity_date_display: string,
     *     vehicle_type_ids: list<int>,
     *     rows: list<array{
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
    public function paidDailyFeeVehiclesForToday(): array
    {
        $today = Carbon::today('Europe/Podgorica');
        $vehicleTypeIds = $this->vehicleEligibility->controlDailyFeeListVehicleTypeIds();

        if ($vehicleTypeIds === []) {
            return [
                'validity_date' => $today->toDateString(),
                'validity_date_display' => $today->format('d.m.Y'),
                'vehicle_type_ids' => [],
                'rows' => [],
            ];
        }

        /** @var Collection<int, Reservation> $rows */
        $rows = Reservation::query()
            ->where('reservation_kind', ReservationKind::DAILY_TICKET)
            ->whereDate('reservation_date', $today)
            ->where('status', 'paid')
            ->whereIn('vehicle_type_id', $vehicleTypeIds)
            ->with(['vehicleType.translations'])
            ->orderBy('license_plate')
            ->orderBy('id')
            ->get();

        return [
            'validity_date' => $today->toDateString(),
            'validity_date_display' => $today->format('d.m.Y'),
            'vehicle_type_ids' => $vehicleTypeIds,
            'rows' => $rows->map(fn (Reservation $r) => $this->formatRow($r))->values()->all(),
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
     *     created_at: string,
     *     coverage_label: string
     * }
     */
    private function formatCheckRow(Reservation $reservation): array
    {
        return array_merge($this->formatRow($reservation), [
            'coverage_label' => $this->coverageLabelFor($reservation),
        ]);
    }

    private function coverageLabelFor(Reservation $reservation): string
    {
        if ($reservation->reservation_kind === ReservationKind::DAILY_TICKET) {
            return 'Dnevna naknada (plaćena)';
        }

        return $reservation->status === 'free'
            ? 'Termini (besplatna potvrda)'
            : 'Termini (plaćena rezervacija)';
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
    private function formatRow(Reservation $reservation): array
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
