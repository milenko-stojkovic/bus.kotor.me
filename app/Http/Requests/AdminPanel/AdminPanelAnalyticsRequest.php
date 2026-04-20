<?php

namespace App\Http\Requests\AdminPanel;

use App\Services\AdminPanel\Reservation\AdminReservationDateBounds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdminPanelAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bounds = app(AdminReservationDateBounds::class);
        $min = $bounds->searchMinDate()->toDateString();
        $max = $bounds->searchMaxDate()->toDateString();

        return [
            'date_from' => ['required', 'date', 'after_or_equal:'.$min, 'before_or_equal:'.$max],
            'date_to' => ['required', 'date', 'after_or_equal:'.$min, 'before_or_equal:'.$max],
            'include_free' => ['nullable', 'boolean'],
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
        });
    }
}

