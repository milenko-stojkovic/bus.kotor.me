<?php

namespace App\Http\Requests\AdminPanel;

use App\Models\TempData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminPanelInsightSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_transaction_id' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'user_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'vehicle_type_id' => ['nullable', 'integer'],
            'license_plate' => ['nullable', 'string', 'max:32', 'regex:/^[A-Z0-9]+$/'],
            'country' => ['nullable', 'string', 'max:2'],
            'status' => ['nullable', Rule::in([
                TempData::STATUS_PENDING,
                TempData::STATUS_PROCESSED,
                TempData::STATUS_LATE_SUCCESS,
                TempData::STATUS_LATE_MANUAL_REVIEW,
                TempData::STATUS_LATE_REJECTED,
                TempData::STATUS_CANCELED,
                TempData::STATUS_EXPIRED,
                // keep compatibility with older sqlite test schemas
                'failed',
            ])],
            'resolution_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $from = (string) $this->input('date_from', '');
            $to = (string) $this->input('date_to', '');
            if ($from !== '' && $to !== '' && $from > $to) {
                $v->errors()->add('date_to', 'Datum „do“ mora biti poslije ili jednak datumu „od“.');
            }

            $hasAny = false;
            foreach ([
                'merchant_transaction_id',
                'date_from',
                'date_to',
                'user_name',
                'email',
                'vehicle_type_id',
                'license_plate',
                'country',
                'status',
                'resolution_reason',
            ] as $k) {
                if ((string) $this->input($k, '') !== '') {
                    $hasAny = true;
                    break;
                }
            }

            if (! $hasAny) {
                $v->errors()->add('merchant_transaction_id', 'Unesi bar jedan kriterijum za pretragu.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('license_plate')) {
            $v = (string) $this->input('license_plate');
            $v = strtoupper($v);
            $v = preg_replace('/[^A-Z0-9]+/', '', $v) ?? '';
            $this->merge(['license_plate' => $v]);
        }
        if ($this->has('country')) {
            $this->merge(['country' => strtoupper(trim((string) $this->input('country')))]);
        }
        if ($this->has('merchant_transaction_id')) {
            $this->merge(['merchant_transaction_id' => trim((string) $this->input('merchant_transaction_id'))]);
        }
        if ($this->has('user_name')) {
            $this->merge(['user_name' => trim((string) $this->input('user_name'))]);
        }
        if ($this->has('email')) {
            $this->merge(['email' => trim((string) $this->input('email'))]);
        }
        if ($this->has('resolution_reason')) {
            $this->merge(['resolution_reason' => trim((string) $this->input('resolution_reason'))]);
        }
    }
}

