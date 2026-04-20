<?php

namespace App\Http\Requests\AdminPanel;

use Illuminate\Foundation\Http\FormRequest;

class AdminPanelCapacityUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'available_parking_slots' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}

