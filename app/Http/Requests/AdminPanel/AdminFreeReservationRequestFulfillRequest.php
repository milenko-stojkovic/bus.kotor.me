<?php

namespace App\Http\Requests\AdminPanel;

use Illuminate\Foundation\Http\FormRequest;

class AdminFreeReservationRequestFulfillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('panel_admin') !== null;
    }

    public function rules(): array
    {
        return [
            'confirm' => ['required', 'accepted'],
        ];
    }
}

