<?php

namespace App\Http\Requests\AdminPanel;

use Illuminate\Foundation\Http\FormRequest;

final class AdminAgencyAdvanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('panel_admin') !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999.99'],
            'direction' => ['required', 'string', 'in:increase,decrease'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}

