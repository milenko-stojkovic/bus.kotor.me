<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreVehicleCategoryChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'old_vehicle_id' => ['required', 'integer'],
            'license_plate' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9]+$/'],
            'old_vehicle_type_id' => ['required', 'integer', 'exists:vehicle_types,id'],
            'requested_vehicle_type_id' => ['required', 'integer', 'exists:vehicle_types,id'],
            'documents' => ['required', 'array', 'min:1', 'max:5'],
            'documents.*' => [
                'required',
                'file',
                'max:10240',
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],
        ];
    }
}
