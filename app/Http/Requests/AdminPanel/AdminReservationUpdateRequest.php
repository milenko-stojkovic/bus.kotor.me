<?php

namespace App\Http\Requests\AdminPanel;

use App\Models\VehicleType;
use App\Services\AdminPanel\Reservation\AdminReservationDateBounds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdminReservationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bounds = app(AdminReservationDateBounds::class);
        $min = $bounds->editMinDate()->toDateString();
        $max = $bounds->editMaxDate()->toDateString();

        return [
            'reservation_date' => ['required', 'date', 'after_or_equal:'.$min, 'before_or_equal:'.$max],
            'drop_off_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
            'pick_up_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
            'user_name' => ['required', 'string', 'max:255', 'regex:/^(?=.*[\p{L}\p{N}]).+$/u'],
            'country' => ['required', 'string', 'max:100'],
            'license_plate' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9]+$/'],
            'vehicle_type_id' => ['required', 'integer', 'exists:vehicle_types,id'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'return_query' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $reservation = $this->route('reservation');
            if (! $reservation) {
                return;
            }
            $vtId = (int) $this->input('vehicle_type_id');
            $vt = VehicleType::query()->find($vtId);
            $current = $reservation->vehicleType;
            if ($vt && $current) {
                if ((float) $vt->price > (float) $current->price) {
                    $v->errors()->add('vehicle_type_id', 'Nije dozvoljena veća kategorija od trenutne.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('license_plate')) {
            $this->merge([
                'license_plate' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('license_plate'))),
            ]);
        }
        if ($this->has('user_name')) {
            $this->merge([
                'user_name' => trim((string) $this->input('user_name')),
            ]);
        }
    }
}
