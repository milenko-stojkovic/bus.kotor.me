<?php

namespace App\Http\Requests\AdminPanel;

use Illuminate\Foundation\Http\FormRequest;

class AdminFreeReservationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }

        $license = $this->input('license_plate');
        if (is_string($license)) {
            $normalized = strtoupper(trim($license));
            $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;
            $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized) ?? $normalized;
            $this->merge(['license_plate' => $normalized]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxDate = now()->addDays(90)->toDateString();

        return [
            'drop_off_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
            'pick_up_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.$maxDate],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'country' => ['required', 'string', 'max:100'],
            'license_plate' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9]+$/'],
            'vehicle_type_id' => ['required', 'integer', 'exists:vehicle_types,id'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
