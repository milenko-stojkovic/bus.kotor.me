<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FzbrStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reservation_date' => ['required', 'date'],
            'drop_off_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
            'pick_up_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],

            // One dropdown per row, values are agency vehicle IDs.
            'vehicles' => ['required', 'array', 'min:1', 'max:9'],
            'vehicles.*' => ['required', 'integer'],

            // Documents: multi-file, images or PDF, total <= 10MB.
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => [
                'required',
                'file',
                'max:10240', // per-file hard stop (10MB); total is checked separately
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp,image/gif,image/avif',
            ],

            'accept_privacy' => ['required', 'accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var mixed $docs */
            $docs = $this->file('documents');
            if (! is_array($docs) || $docs === []) {
                return;
            }

            $sum = 0;
            foreach ($docs as $f) {
                if ($f instanceof \Illuminate\Http\UploadedFile) {
                    $sum += (int) $f->getSize();
                }
            }

            if ($sum > 10 * 1024 * 1024) {
                $validator->errors()->add('documents', 'Ukupna veličina dokumenata ne smije preći 10 MB.');
            }
        });
    }
}

