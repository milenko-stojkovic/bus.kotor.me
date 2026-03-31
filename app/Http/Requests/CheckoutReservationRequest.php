<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutReservationRequest extends FormRequest
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
        $authUser = $this->user();
        $usingSavedVehicle = $authUser !== null && $this->filled('vehicle_id');

        return [
            // Opciono: ako frontend pošalje, koristi se za dupli klik; inače backend generiše UUID
            'merchant_transaction_id' => ['nullable', 'string', 'max:64'],
            'vehicle_id' => [
                'nullable',
                'integer',
                Rule::exists('vehicles', 'id')->where(fn ($q) => $q->where('user_id', $authUser?->id)),
            ],
            'drop_off_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
            'pick_up_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'name' => [$usingSavedVehicle ? 'nullable' : 'required', 'string', 'min:2', 'max:255'],
            'country' => [$usingSavedVehicle ? 'nullable' : 'required', 'string', 'max:100'],
            'license_plate' => [
                $usingSavedVehicle ? 'nullable' : 'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9]+$/',
            ],
            'vehicle_type_id' => [$usingSavedVehicle ? 'nullable' : 'required', 'integer', 'exists:vehicle_types,id'],
            'email' => [$usingSavedVehicle ? 'nullable' : 'required', 'email', 'max:255'],

            // Guest UX hard requirements (not persisted; audit/UI later).
            'accept_terms' => ['required', 'accepted'],
            'accept_privacy' => ['required', 'accepted'],
        ];
    }
}
