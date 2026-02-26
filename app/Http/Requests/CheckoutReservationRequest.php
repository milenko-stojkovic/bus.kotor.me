<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Opciono: ako frontend pošalje, koristi se za dupli klik; inače backend generiše UUID
            'merchant_transaction_id' => ['nullable', 'string', 'max:64'],
            'drop_off_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
            'pick_up_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'user_name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:100'],
            'license_plate' => ['required', 'string', 'max:50'],
            'vehicle_type_id' => ['required', 'integer', 'exists:vehicle_types,id'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
