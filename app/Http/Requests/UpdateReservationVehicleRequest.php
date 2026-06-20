<?php

namespace App\Http\Requests;

use App\Models\Reservation;
use App\Models\Vehicle;
use App\Services\Reservation\PanelReservationListService;
use App\Services\Reservation\VehicleReplacementCandidateService;
use App\Support\UiText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateReservationVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => [
                'required',
                'integer',
                Rule::exists('vehicles', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $resId = (int) $this->route('id');
            $vehicleId = (int) $this->input('vehicle_id');

            $reservation = Reservation::query()
                ->where('user_id', $this->user()->id)
                ->with('vehicleType')
                ->find($resId);

            if (! $reservation || ! PanelReservationListService::isUpcoming($reservation)) {
                $locale = app()->getLocale();
                $validator->errors()->add(
                    'vehicle_id',
                    UiText::t('panel', 'vehicle_change_not_upcoming', 'This reservation cannot be changed.', $locale)
                );

                return;
            }

            if ($reservation->isDailyTicket()) {
                $locale = app()->getLocale();
                $validator->errors()->add(
                    'vehicle_id',
                    UiText::t(
                        'panel',
                        'upcoming_plate_change_unavailable_daily_fee',
                        $locale === 'cg'
                            ? 'Promjena tablice nije dostupna za dnevnu naknadu.'
                            : 'Plate change is not available for Daily fee reservations.',
                        $locale
                    )
                );

                return;
            }

            $vehicle = Vehicle::query()
                ->where('user_id', $this->user()->id)
                ->with('vehicleType')
                ->find($vehicleId);

            if (! $vehicle) {
                return;
            }

            $svc = app(VehicleReplacementCandidateService::class);

            if (! $svc->isVehicleCategoryAllowed($reservation, $vehicle)) {
                $locale = app()->getLocale();
                $validator->errors()->add(
                    'vehicle_id',
                    UiText::t(
                        'panel',
                        'vehicle_change_category_too_high',
                        'You can only switch to a vehicle of the same or a lower category (price).',
                        $locale
                    )
                );
                return;
            }

            // Hard membership check: chosen vehicle must be an actual candidate for this reservation.
            $candidates = $svc->candidatesForReservation($this->user(), $reservation);
            $isMember = $candidates->contains(fn (Vehicle $v) => (int) $v->id === (int) $vehicle->id);
            if (! $isMember) {
                $locale = app()->getLocale();
                $validator->errors()->add(
                    'vehicle_id',
                    UiText::t(
                        'panel',
                        'vehicle_change_not_allowed_candidate',
                        'Odabrano vozilo nije dozvoljena zamjena za ovu rezervaciju.',
                        $locale
                    )
                );
                return;
            }

            // Conflict check (drop=drop OR pick=pick on same date). Cross-match is allowed.
            $conflict = $svc->hasConflictWithUpcoming(
                $this->user(),
                $vehicle->id,
                $reservation,
                ignoreReservationIds: [$resId]
            );
            if ($conflict) {
                $locale = app()->getLocale();
                $validator->errors()->add(
                    'vehicle_id',
                    UiText::t(
                        'panel',
                        'vehicle_change_conflict',
                        'Selected vehicle is not available for this reservation (time slot conflict).',
                        $locale
                    )
                );
            }
        });
    }
}
