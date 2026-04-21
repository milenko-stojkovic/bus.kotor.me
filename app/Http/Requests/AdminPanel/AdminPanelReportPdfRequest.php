<?php

namespace App\Http\Requests\AdminPanel;

use App\Services\AdminPanel\Reports\AdminReportsCreatedAtBounds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminPanelReportPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bounds = app(AdminReportsCreatedAtBounds::class);
        $min = $bounds->minDate()->toDateString();
        $max = $bounds->maxDate()->toDateString();

        return [
            'when' => ['required', Rule::in(['daily', 'monthly', 'yearly', 'period'])],
            'kind' => ['required', Rule::in(['by_payment', 'by_realization', 'by_vehicle_type'])],

            // Daily
            'date' => ['nullable', 'date', 'after_or_equal:'.$min, 'before_or_equal:'.$max],

            // Monthly
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:1970', 'max:2100'],

            // Period
            'date_from' => ['nullable', 'date', 'after_or_equal:'.$min, 'before_or_equal:'.$max],
            'date_to' => ['nullable', 'date', 'after_or_equal:'.$min, 'before_or_equal:'.$max],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $bounds = app(AdminReportsCreatedAtBounds::class);
        $min = $bounds->minDate()->toDateString();
        $max = $bounds->maxDate()->toDateString();
        $minYear = (int) substr($min, 0, 4);
        $maxYear = (int) substr($max, 0, 4);

        $validator->after(function (Validator $v) use ($min, $max, $minYear, $maxYear): void {
            $when = (string) $this->input('when', '');

            if ($when === 'daily') {
                if ((string) $this->input('date', '') === '') {
                    $v->errors()->add('date', 'Datum je obavezan.');
                }

                return;
            }

            if ($when === 'monthly') {
                $m = (int) $this->input('month', 0);
                $y = (int) $this->input('year', 0);
                if ($m < 1 || $m > 12) {
                    $v->errors()->add('month', 'Mjesec je obavezan.');
                }
                if ($y < 1970 || $y > 2100) {
                    $v->errors()->add('year', 'Godina je obavezna.');
                }
                if ($y < $minYear || $y > $maxYear) {
                    $v->errors()->add('year', 'Izabrana godina je van dozvoljenog opsega.');
                }
                if ($v->errors()->isNotEmpty()) {
                    return;
                }

                $from = sprintf('%04d-%02d-01', $y, $m);
                $to = date('Y-m-t', strtotime($from));
                // Accept any month that overlaps the created_at date bounds.
                if ($to < $min || $from > $max) {
                    $v->errors()->add('month', 'Izabrani mjesec je van dozvoljenog opsega.');
                }

                return;
            }

            if ($when === 'yearly') {
                $y = (int) $this->input('year', 0);
                if ($y < 1970 || $y > 2100) {
                    $v->errors()->add('year', 'Godina je obavezna.');
                    return;
                }
                if ($y < $minYear || $y > $maxYear) {
                    $v->errors()->add('year', 'Izabrana godina je van dozvoljenog opsega.');
                    return;
                }
                $from = sprintf('%04d-01-01', $y);
                $to = sprintf('%04d-12-31', $y);
                // Accept any year that overlaps the created_at date bounds.
                if ($to < $min || $from > $max) {
                    $v->errors()->add('year', 'Izabrana godina je van dozvoljenog opsega.');
                }

                return;
            }

            if ($when === 'period') {
                $from = (string) $this->input('date_from', '');
                $to = (string) $this->input('date_to', '');
                if ($from === '') {
                    $v->errors()->add('date_from', 'Datum „od“ je obavezan.');
                }
                if ($to === '') {
                    $v->errors()->add('date_to', 'Datum „do“ je obavezan.');
                }
                if ($from !== '' && $to !== '' && $from > $to) {
                    $v->errors()->add('date_to', 'Datum „do“ mora biti poslije ili jednak datumu „od“.');
                }

                return;
            }
        });
    }
}

