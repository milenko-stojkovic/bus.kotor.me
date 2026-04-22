<?php

namespace App\Http\Requests;

use App\Models\ListOfTimeSlot;
use App\Services\Reservation\DuplicateReservationAttemptService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FreeReservationRequestStoreRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $vehicles = $this->input('vehicles');
        if (is_array($vehicles)) {
            $vehicles = array_values(array_filter($vehicles, fn ($v) => is_array($v)));
            $vehicles = array_map(function (array $v): array {
                $lp = $v['license_plate'] ?? null;
                return [
                    ...$v,
                    'license_plate' => DuplicateReservationAttemptService::normalizeLicensePlate(is_string($lp) ? $lp : ''),
                ];
            }, $vehicles);
            $this->merge(['vehicles' => $vehicles]);
        }

        foreach (['institution_name', 'institution_email', 'institution_phone'] as $k) {
            $v = $this->input($k);
            if (is_string($v)) {
                $this->merge([$k => trim($v)]);
            }
        }
        $country = $this->input('country');
        if (is_string($country)) {
            $this->merge(['country' => trim($country)]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'drop_off_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
            'pick_up_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],

            'institution_name' => ['required', 'string', 'min:2', 'max:255'],
            'country' => ['required', 'string', 'max:100'],
            'institution_email' => ['required', 'email', 'max:255'],
            'institution_phone' => ['required', 'string', 'max:32', 'regex:/^\+\d+$/'],

            'vehicles' => ['required', 'array', 'min:1', 'max:9'],
            'vehicles.*.license_plate' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9]+$/'],
            'vehicles.*.vehicle_type_id' => ['required', 'integer', 'exists:vehicle_types,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $date = $this->input('reservation_date');
            if (is_string($date) && $date !== '') {
                try {
                    $d = Carbon::parse($date)->startOfDay();
                    if ($d->gt(now()->addDays(90)->endOfDay())) {
                        $v->errors()->add('reservation_date', __('Invalid date.'));
                    }
                } catch (\Throwable) {
                    // base validator will handle
                }
            }

            $dropId = (int) $this->input('drop_off_time_slot_id');
            $pickId = (int) $this->input('pick_up_time_slot_id');
            if ($dropId > 0 && $pickId > 0) {
                $drop = ListOfTimeSlot::query()->find($dropId);
                $pick = ListOfTimeSlot::query()->find($pickId);
                if ($drop && $pick) {
                    // Basic sanity: pick-up cannot be before drop-off.
                    if ($pick->id < $drop->id) {
                        $v->errors()->add('pick_up_time_slot_id', __('Invalid time slots.'));
                    }
                }
            }
        });
    }
}

