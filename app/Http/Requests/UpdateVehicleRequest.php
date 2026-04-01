<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $license = $this->input('license_plate');
        if (is_string($license)) {
            $normalized = strtoupper(trim($license));
            $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;
            $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized) ?? $normalized;
            $this->merge(['license_plate' => $normalized]);
        }
    }

    public function rules(): array
    {
        $vehicleId = (int) $this->route('vehicle');

        return [
            'license_plate' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('vehicles')
                    ->ignore($vehicleId)
                    ->where(fn ($q) => $q->where('user_id', $this->user()->id)),
            ],
            'vehicle_type_id' => ['required', 'integer', 'exists:vehicle_types,id'],
        ];
    }
}
