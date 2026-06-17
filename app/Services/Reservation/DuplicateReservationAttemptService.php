<?php

namespace App\Services\Reservation;

use App\Exceptions\DuplicateTerminiReservationException;
use App\Models\Reservation;
use App\Models\TempData;
use App\Support\ReservationKind;
use App\Support\UiText;
use Illuminate\Database\Eloquent\Builder;

class DuplicateReservationAttemptService
{
    /** @var list<string> */
    public const ACTIVE_RESERVATION_STATUSES = [
        'paid',
        'free',
    ];

    public static function normalizeLicensePlate(?string $licensePlate): string
    {
        $v = strtoupper(trim((string) $licensePlate));
        // Tolerate common Montenegrin/Serbian Latin diacritics in manual input.
        // OCR normalization is also ASCII-only; keep backend tolerant so that manual input isn't rejected.
        $v = strtr($v, [
            'Ž' => 'Z',
            'Š' => 'S',
            'Č' => 'C',
            'Ć' => 'C',
            'Đ' => 'D',
        ]);
        $v = preg_replace('/\s+/', '', $v) ?? $v;
        $v = preg_replace('/[^A-Z0-9]/', '', $v) ?? $v;

        return $v;
    }

    public function conflictMessage(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return UiText::t(
            'booking',
            'duplicate_termini_slot',
            $locale === 'cg'
                ? 'Za istu registarsku tablicu već postoji rezervacija za odabrani datum sa istim vremenom dolaska ili istim vremenom odlaska.'
                : 'A reservation already exists for this license plate on the selected date with the same arrival time or the same departure time.',
            $locale,
        );
    }

    /**
     * Duplicate attempt definition (Termini only):
     * - same reservation_date
     * - same normalized license_plate
     * - same drop_off_time_slot_id OR same pick_up_time_slot_id
     *
     * Cross-match (drop=pick / pick=drop) is intentionally NOT counted.
     *
     * Also checks active pending temp_data holds for unpaid card checkouts.
     */
    public function existsConflict(
        string $date,
        string $licensePlate,
        int $dropOffSlotId,
        int $pickUpSlotId,
        ?int $exceptReservationId = null,
        ?int $exceptTempDataId = null,
    ): bool {
        $plate = self::normalizeLicensePlate($licensePlate);
        if ($plate === '') {
            return false;
        }

        if ($this->hasReservationConflict($date, $plate, $dropOffSlotId, $pickUpSlotId, $exceptReservationId)) {
            return true;
        }

        return $this->hasPendingTempDataConflict(
            $date,
            $plate,
            $dropOffSlotId,
            $pickUpSlotId,
            $exceptTempDataId,
        );
    }

    /**
     * @throws DuplicateTerminiReservationException
     */
    public function assertNoConflict(
        string $date,
        string $licensePlate,
        int $dropOffSlotId,
        int $pickUpSlotId,
        ?int $exceptReservationId = null,
        ?int $exceptTempDataId = null,
        ?string $locale = null,
    ): void {
        if ($this->existsConflict($date, $licensePlate, $dropOffSlotId, $pickUpSlotId, $exceptReservationId, $exceptTempDataId)) {
            throw new DuplicateTerminiReservationException($this->conflictMessage($locale));
        }
    }

    private function hasReservationConflict(
        string $date,
        string $normalizedPlate,
        int $dropOffSlotId,
        int $pickUpSlotId,
        ?int $exceptReservationId,
    ): bool {
        $query = $this->timeSlotsReservationQuery($date, $dropOffSlotId, $pickUpSlotId);

        if ($exceptReservationId !== null) {
            $query->whereKeyNot($exceptReservationId);
        }

        return $this->queryHasMatchingPlate($query, $normalizedPlate);
    }

    private function hasPendingTempDataConflict(
        string $date,
        string $normalizedPlate,
        int $dropOffSlotId,
        int $pickUpSlotId,
        ?int $exceptTempDataId,
    ): bool {
        $query = TempData::query()
            ->where('status', TempData::STATUS_PENDING)
            ->whereDate('reservation_date', $date)
            ->where(function (Builder $q): void {
                $q->where('reservation_kind', ReservationKind::TIME_SLOTS)
                    ->orWhereNull('reservation_kind');
            })
            ->where(function (Builder $q) use ($dropOffSlotId, $pickUpSlotId): void {
                $q->where('drop_off_time_slot_id', $dropOffSlotId)
                    ->orWhere('pick_up_time_slot_id', $pickUpSlotId);
            });

        if ($exceptTempDataId !== null) {
            $query->whereKeyNot($exceptTempDataId);
        }

        return $this->queryHasMatchingPlate($query, $normalizedPlate);
    }

    private function timeSlotsReservationQuery(string $date, int $dropOffSlotId, int $pickUpSlotId): Builder
    {
        return Reservation::query()
            ->whereDate('reservation_date', $date)
            ->whereIn('status', self::ACTIVE_RESERVATION_STATUSES)
            ->where(function (Builder $q): void {
                $q->where('reservation_kind', ReservationKind::TIME_SLOTS)
                    ->orWhereNull('reservation_kind');
            })
            ->where(function (Builder $q) use ($dropOffSlotId, $pickUpSlotId): void {
                $q->where('drop_off_time_slot_id', $dropOffSlotId)
                    ->orWhere('pick_up_time_slot_id', $pickUpSlotId);
            });
    }

    /**
     * @param  Builder<Reservation>|Builder<TempData>  $query
     */
    private function queryHasMatchingPlate(Builder $query, string $normalizedPlate): bool
    {
        foreach ($query->get(['id', 'license_plate']) as $row) {
            if (self::normalizeLicensePlate($row->license_plate) === $normalizedPlate) {
                return true;
            }
        }

        return false;
    }
}
