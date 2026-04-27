<?php

namespace App\Http\Requests\AdminPanel;

use Illuminate\Foundation\Http\FormRequest;

class AdminFreeReservationRequestUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('panel_admin') !== null;
    }

    public function rules(): array
    {
        return [
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'segments' => ['required', 'array', 'min:1', 'max:5'],
            'segments.*.id' => ['required', 'integer'],
            'segments.*.drop_off_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
            'segments.*.pick_up_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
        ];
    }
}

