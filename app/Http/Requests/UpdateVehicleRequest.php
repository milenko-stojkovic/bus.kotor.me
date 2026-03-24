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

    public function rules(): array
    {
        $vehicleId = (int) $this->route('vehicle');

        return [
            'license_plate' => [
                'required',
                'string',
                'max:50',
                Rule::unique('vehicles')
                    ->ignore($vehicleId)
                    ->where(fn ($q) => $q->where('user_id', $this->user()->id)),
            ],
            'vehicle_type_id' => ['required', 'integer', 'exists:vehicle_types,id'],
        ];
    }
}
