<?php

namespace App\Http\Requests\Panel;

use App\Services\AgencyAdvance\AgencyAdvanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class PanelAdvanceTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $svc = app(AgencyAdvanceService::class);
            $amount = $this->input('amount');

            if (! $svc->isValidTopupAmount($amount)) {
                $validator->errors()->add('amount', 'Iznos avansne uplate mora biti cio broj eura i završavati se na 0 ili 5.');
                return;
            }

            // Normalize: store as "N.00"
            $s = is_scalar($amount) ? (string) $amount : '';
            $s = trim(str_replace(',', '.', $s));
            $euros = (int) (explode('.', $s, 2)[0] ?? 0);
            $this->merge(['amount' => number_format($euros, 2, '.', '')]);
        });
    }
}

