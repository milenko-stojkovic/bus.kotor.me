<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use App\Models\SystemConfig;
use App\Support\UiText;
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
            'segments' => ['required', 'array', 'min:1', 'max:5'],
            'segments.*.drop_off_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
            'segments.*.pick_up_time_slot_id' => ['required', 'integer', 'exists:list_of_time_slots,id'],
            'segments.*.vehicles' => ['required', 'array', 'min:1'],
            'segments.*.vehicles.*' => ['required', 'integer'],

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
            $maxVehicles = (int) SystemConfig::availableParkingSlots();
            if ($maxVehicles <= 0) {
                $maxVehicles = 9;
            }
            $segments = $this->input('segments');
            if (is_array($segments)) {
                foreach ($segments as $i => $seg) {
                    $vehicles = is_array($seg['vehicles'] ?? null) ? $seg['vehicles'] : [];
                    $vehicleIds = array_values(array_filter(array_map(
                        fn ($v) => is_scalar($v) ? (int) $v : 0,
                        $vehicles
                    ), fn (int $v) => $v > 0));

                    if ($maxVehicles > 0 && count($vehicleIds) > $maxVehicles) {
                        $locale = app()->getLocale();
                        $msg = UiText::t(
                            'panel',
                            'fzbr_max_vehicles_per_segment',
                            'Maximum number of vehicles per segment is :max.',
                            $locale
                        );
                        $msg = str_replace(':max', (string) $maxVehicles, $msg);
                        $validator->errors()->add("segments.$i.vehicles", $msg);
                    }

                    if (count($vehicleIds) !== count(array_unique($vehicleIds))) {
                        $locale = app()->getLocale();
                        $validator->errors()->add(
                            "segments.$i.vehicles",
                            UiText::t(
                                'panel',
                                'fzbr_vehicle_duplicate_in_segment',
                                'The same vehicle cannot be selected twice in the same segment.',
                                $locale
                            )
                        );
                    }
                }
            }

            // Validate that all submitted vehicles belong to the authenticated agency user.
            $user = $this->user();
            if ($user && is_array($segments)) {
                $allVehicleIds = [];
                foreach ($segments as $seg) {
                    $vehicles = is_array($seg['vehicles'] ?? null) ? $seg['vehicles'] : [];
                    foreach ($vehicles as $v) {
                        if (is_scalar($v) && (int) $v > 0) {
                            $allVehicleIds[] = (int) $v;
                        }
                    }
                }
                $allVehicleIds = array_values(array_unique($allVehicleIds));
                if ($allVehicleIds !== []) {
                    $count = (int) DB::table('vehicles')
                        ->where('user_id', $user->id)
                        ->whereIn('id', $allVehicleIds)
                        ->count();
                    if ($count !== count($allVehicleIds)) {
                        $locale = app()->getLocale();
                        $validator->errors()->add(
                            'segments',
                            UiText::t('panel', 'fzbr_invalid_vehicles_selection', 'Invalid vehicles selection.', $locale)
                        );
                    }
                }
            }

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
                $locale = app()->getLocale();
                $validator->errors()->add(
                    'documents',
                    UiText::t('panel', 'fzbr_documents_total_too_big', 'Total document size must not exceed 10 MB.', $locale)
                );
            }
        });
    }
}

