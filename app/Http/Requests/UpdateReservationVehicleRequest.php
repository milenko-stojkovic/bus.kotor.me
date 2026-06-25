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

            if (! PanelReservationListService::allowsPlateChange($reservation)) {
                $locale = app()->getLocale();
                $validator->errors()->add(
                    'vehicle_id',
                    UiText::t(
                        'panel',
                        'upcoming_plate_change_unavailable_daily_fee_today',
                        $locale === 'cg'
                            ? 'Promjena tablice za dnevnu naknadu nije dostupna za tekući dan.'
                            : 'Plate change for Daily fee is not available on the current day.',
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

            // Termini only: conflict check (drop=drop OR pick=pick on same date). Daily fee skips slots.
            if (! $reservation->isDailyTicket() && $svc->hasConflictWithUpcoming(
                $this->user(),
                $vehicle->id,
                $reservation,
                ignoreReservationIds: [$resId]
            )) {
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
