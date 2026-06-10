<?php

namespace App\Http\Requests\Panel;

use App\Services\AgencyAdvance\AgencyAdvanceService;
use App\Support\UiText;
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
                $locale = app()->getLocale();
                $validator->errors()->add(
                    'amount',
                    UiText::t(
                        'panel',
                        'advance_topup_amount_invalid',
                        'Top-up amount must be a whole number of euros and end with 0 or 5.',
                        $locale
                    )
                );
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

