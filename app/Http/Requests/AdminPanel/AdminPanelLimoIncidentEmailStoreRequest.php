<?php

namespace App\Http\Requests\AdminPanel;

use Illuminate\Foundation\Http\FormRequest;

class AdminPanelLimoIncidentEmailStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limo_incident_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('limo_incident_email')) {
            $this->merge([
                'limo_incident_email' => mb_strtolower(trim((string) $this->input('limo_incident_email'))),
            ]);
        }
    }
}
