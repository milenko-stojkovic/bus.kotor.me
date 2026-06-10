<?php

namespace App\Http\Requests\Control;

use App\Services\Control\DailyFeeControlService;
use Illuminate\Foundation\Http\FormRequest;

final class DailyFeeControlCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $plate = $this->input('license_plate');
        if (! is_string($plate)) {
            return;
        }

        $this->merge([
            'license_plate' => app(DailyFeeControlService::class)->normalizePlate($plate),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'license_plate' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'license_plate.required' => 'Unesite registarsku tablicu.',
            'license_plate.regex' => 'Tablica mora sadržavati samo velika slova i brojeve (bez razmaka).',
        ];
    }
}
