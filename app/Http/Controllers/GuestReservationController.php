<?php

namespace App\Http\Controllers;

use App\Models\DailyParkingData;
use App\Models\ListOfTimeSlot;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuestReservationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $locale = app()->getLocale();

        $dateStr = (string) $request->query('reservation_date', '');
        $selectedDate = $this->parseAllowedDate($dateStr);

        $arrivalId = $this->asIntOrNull($request->query('drop_off_time_slot_id'));
        $departureId = $this->asIntOrNull($request->query('pick_up_time_slot_id'));

        $allSlots = ListOfTimeSlot::query()->orderBy('id')->get();
        $dailyBySlotId = collect();
        if ($selectedDate) {
            $dailyBySlotId = DailyParkingData::query()
                ->whereDate('date', $selectedDate->toDateString())
                ->get()
                ->keyBy('time_slot_id');
        }

        $arrivalSlots = [];
        if ($selectedDate) {
            $arrivalSlots = $allSlots->map(function (ListOfTimeSlot $slot) use ($selectedDate, $dailyBySlotId, $locale) {
                $daily = $dailyBySlotId->get($slot->id);
                $spotsLeft = $daily ? $daily->availableCapacity() : 0;
                $isFree = $this->isFreeSlot($slot);
                $isPastForToday = $this->isPastSlotForToday($slot, $selectedDate);
                $isFull = ! $isFree && $spotsLeft < 1;
                $disabled = $isPastForToday || $isFull;

                return [
                    'id' => $slot->id,
                    'label' => $this->formatSlotLabel($slot->time_slot, $spotsLeft, $locale),
                    'spots_left' => $spotsLeft,
                    'is_free' => $isFree,
                    'disabled' => $disabled,
                ];
            })->all();
        }

        $selectedArrivalSlot = $arrivalId ? $allSlots->firstWhere('id', $arrivalId) : null;
        $selectedDepartureSlot = $departureId ? $allSlots->firstWhere('id', $departureId) : null;

        $departureSlots = [];
        $departureDisabled = $selectedDate === null || $selectedArrivalSlot === null;
        if (! $departureDisabled && $selectedArrivalSlot) {
            $arrivalStart = $selectedArrivalSlot->getStartTimeForDate($selectedDate);

            $departureSlots = $allSlots->map(function (ListOfTimeSlot $slot) use ($selectedDate, $dailyBySlotId, $arrivalStart, $locale) {
                $daily = $dailyBySlotId->get($slot->id);
                $spotsLeft = $daily ? $daily->availableCapacity() : 0;
                $isFree = $this->isFreeSlot($slot);
                $isPastForToday = $this->isPastSlotForToday($slot, $selectedDate);
                $isFull = ! $isFree && $spotsLeft < 1;

                $slotStart = $slot->getStartTimeForDate($selectedDate);
                $beforeArrival = $arrivalStart && $slotStart ? $slotStart->lt($arrivalStart) : false;

                $disabled = $isPastForToday || $isFull || $beforeArrival;

                return [
                    'id' => $slot->id,
                    'label' => $this->formatSlotLabel($slot->time_slot, $spotsLeft, $locale),
                    'spots_left' => $spotsLeft,
                    'is_free' => $isFree,
                    'disabled' => $disabled,
                    'before_arrival' => $beforeArrival,
                ];
            })->all();
        }

        // Reset departure if it is no longer valid.
        if ($departureId && ! $departureDisabled) {
            $validDepartureIds = collect($departureSlots)->filter(fn ($s) => ! $s['disabled'])->pluck('id')->all();
            if (! in_array($departureId, $validDepartureIds, true)) {
                $departureId = null;
                $selectedDepartureSlot = null;
            }
        }

        $vehicleTypes = VehicleType::query()
            ->with('translations')
            ->orderBy('id')
            ->get();

        $countries = (array) config('countries', []);

        $vehicleTypeId = $this->asIntOrNull($request->query('vehicle_type_id'));
        $selectedVehicleType = $vehicleTypeId ? $vehicleTypes->firstWhere('id', $vehicleTypeId) : null;

        $isFreeReservation = false;
        $paidAmount = null;
        if ($selectedArrivalSlot && $selectedDepartureSlot) {
            $isFreeReservation = $this->isFreeReservation($selectedArrivalSlot, $selectedDepartureSlot);
            if (! $isFreeReservation && $selectedVehicleType && is_numeric((string) $selectedVehicleType->price)) {
                $paidAmount = number_format((float) $selectedVehicleType->price, 2, '.', '');
            }
        }

        return view('guest.reserve', [
            'selected_date' => $selectedDate?->toDateString(),
            'arrival_id' => $arrivalId,
            'departure_id' => $departureId,
            'arrival_slots' => $arrivalSlots,
            'departure_slots' => $departureSlots,
            'departure_disabled' => $departureDisabled,
            'vehicle_types' => $vehicleTypes,
            'countries' => $countries,
            'paid_amount' => $paidAmount,
            'is_free_reservation' => $isFreeReservation,
        ]);
    }

    private function parseAllowedDate(string $dateStr): ?Carbon
    {
        $dateStr = trim($dateStr);
        if ($dateStr === '') {
            return null;
        }

        try {
            $d = Carbon::parse($dateStr)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $today = now()->startOfDay();
        if ($d->lt($today)) {
            return null;
        }
        if ($d->gt($today->copy()->addDays(90))) {
            return null;
        }

        return $d;
    }

    private function asIntOrNull(mixed $v): ?int
    {
        if ($v === null) {
            return null;
        }
        if (is_int($v)) {
            return $v;
        }
        if (! is_string($v)) {
            return null;
        }
        $v = trim($v);
        if ($v === '' || ! preg_match('/^\d+$/', $v)) {
            return null;
        }

        return (int) $v;
    }

    private function isPastSlotForToday(ListOfTimeSlot $slot, Carbon $selectedDate): bool
    {
        $today = now()->startOfDay();
        if (! $selectedDate->isSameDay($today)) {
            return false;
        }

        $end = $slot->getEndTimeForDate($selectedDate);
        if (! $end) {
            return false;
        }

        return now()->gte($end);
    }

    private function formatSlotLabel(string $timeSlot, int $spotsLeft, string $locale): string
    {
        if ($locale === 'cg') {
            return sprintf('%s (%d slobodna mjesta)', $timeSlot, $spotsLeft);
        }

        return sprintf('%s (%d spots left)', $timeSlot, $spotsLeft);
    }

    private function isFreeSlot(ListOfTimeSlot $slot): bool
    {
        // Free zones: 00:00-07:00 and 20:00-24:00
        $start = $slot->getStartTimeForDate(now()->startOfDay());
        $end = $slot->getEndTimeForDate(now()->startOfDay());
        if (! $start || ! $end) {
            return false;
        }

        $minutesStart = ((int) $start->format('H')) * 60 + (int) $start->format('i');
        $minutesEnd = ((int) $end->format('H')) * 60 + (int) $end->format('i');

        $inMorning = $minutesStart >= 0 && $minutesEnd <= 7 * 60;
        $inEvening = $minutesStart >= 20 * 60 && $minutesEnd <= 24 * 60;

        return $inMorning || $inEvening;
    }

    private function isFreeReservation(ListOfTimeSlot $arrival, ListOfTimeSlot $departure): bool
    {
        // Keep V1 semantics (known combos):
        // allowed: 1->1, 1->41, 41->41; disallowed: 41->1
        $a = (int) $arrival->id;
        $d = (int) $departure->id;
        if ($a === 41 && $d === 1) {
            return false;
        }
        if (($a === 1 && ($d === 1 || $d === 41)) || ($a === 41 && $d === 41)) {
            return true;
        }

        // Fallback: both slots must be in free zones.
        return $this->isFreeSlot($arrival) && $this->isFreeSlot($departure);
    }
}

