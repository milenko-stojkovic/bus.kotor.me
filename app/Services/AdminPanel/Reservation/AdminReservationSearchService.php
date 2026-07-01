<?php

namespace App\Services\AdminPanel\Reservation;

use App\Models\Reservation;
use App\Support\MontenegroLicensePlate;
use App\Support\ReservationKind;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminReservationSearchService
{
    public function __construct(
        private AdminReservationSearchHeuristic $heuristic,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $q = Reservation::query()
            ->with(['pickUpTimeSlot', 'dropOffTimeSlot', 'vehicleType.translations', 'user'])
            ->orderByDesc('reservation_date')
            ->orderByDesc('id');

        $this->applyFilters($q, $filters);

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $q, array $filters): void
    {
        if (! empty($filters['merchant_transaction_id'])) {
            $mtid = trim((string) $filters['merchant_transaction_id']);
            if ($mtid !== '') {
                $q->where('merchant_transaction_id', $mtid);
            }
        }

        if (! empty($filters['user_id'])) {
            $q->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['date_interval']) && $filters['date_interval'] === true) {
            if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
                $q->whereBetween('reservation_date', [
                    (string) $filters['date_from'],
                    (string) $filters['date_to'],
                ]);
            }
        } elseif (! empty($filters['date_single'])) {
            $q->whereDate('reservation_date', (string) $filters['date_single']);
        }

        if (! empty($filters['name_patterns']) && is_array($filters['name_patterns'])) {
            $patterns = $filters['name_patterns'];
            $q->where(function (Builder $qq) use ($patterns): void {
                foreach ($patterns as $p) {
                    $qq->orWhere('user_name', 'like', $p);
                }
            });
        }

        if (! empty($filters['email_patterns']) && is_array($filters['email_patterns'])) {
            $patterns = $filters['email_patterns'];
            $q->where(function (Builder $qq) use ($patterns): void {
                foreach ($patterns as $p) {
                    $qq->orWhere('email', 'like', $p);
                }
            });
        }

        if (! empty($filters['vehicle_type_id'])) {
            $q->where('vehicle_type_id', (int) $filters['vehicle_type_id']);
        }

        if (! empty($filters['license_plate'])) {
            $plate = MontenegroLicensePlate::normalizeAscii((string) $filters['license_plate']);
            if ($plate !== '') {
                $q->whereRaw("REPLACE(UPPER(license_plate), ' ', '') LIKE ?", ['%'.$plate.'%']);
            }
        }

        if (! empty($filters['country'])) {
            $q->where('country', (string) $filters['country']);
        }

        if (! empty($filters['status']) && in_array($filters['status'], ['paid', 'free'], true)) {
            $q->where('status', $filters['status']);
        }

        if (! empty($filters['reservation_kind']) && in_array($filters['reservation_kind'], ReservationKind::ALL, true)) {
            $q->where('reservation_kind', $filters['reservation_kind']);
        }
    }

    /**
     * Build search filters from validated request input.
     *
     * When agency_user_id is set, contact fields (name/email/country) are ignored unless
     * $narrowByContact is true — they are often auto-filled from the agency account and must
     * not constrain reservations.user_name / snapshot email.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function buildFiltersFromValidated(array $validated, bool $useInterval, bool $narrowByContact = false): array
    {
        $filters = [];
        $agencySelected = ! empty($validated['agency_user_id']);

        if (! empty($validated['merchant_transaction_id'])) {
            $filters['merchant_transaction_id'] = trim((string) $validated['merchant_transaction_id']);
        }

        if ($agencySelected) {
            $filters['user_id'] = (int) $validated['agency_user_id'];
        }

        if ($useInterval) {
            if (! empty($validated['date_from']) && ! empty($validated['date_to'])) {
                $filters['date_interval'] = true;
                $filters['date_from'] = $validated['date_from'];
                $filters['date_to'] = $validated['date_to'];
            }
        } elseif (! empty($validated['date_single'])) {
            $filters['date_single'] = $validated['date_single'];
        }

        if (! empty($validated['vehicle_type_id'])) {
            $filters['vehicle_type_id'] = (int) $validated['vehicle_type_id'];
        }

        if (! empty($validated['license_plate'])) {
            $filters['license_plate'] = $validated['license_plate'];
        }

        if (! empty($validated['status'])) {
            $filters['status'] = $validated['status'];
        }

        if (! empty($validated['reservation_kind'])) {
            $filters['reservation_kind'] = (string) $validated['reservation_kind'];
        }

        if (! $agencySelected || $narrowByContact) {
            if (! empty($validated['country'])) {
                $filters['country'] = $validated['country'];
            }

            $filters = array_merge(
                $filters,
                $this->buildHeuristicPatterns($validated['name'] ?? null, $validated['email'] ?? null),
            );
        }

        return $filters;
    }

    public function shouldApplyContactFilters(bool $agencySelected, bool $narrowByContact): bool
    {
        return ! $agencySelected || $narrowByContact;
    }

    /**
     * @return array{name_patterns?:list<string>,email_patterns?:list<string>}
     */
    public function buildHeuristicPatterns(?string $name, ?string $email): array
    {
        $out = [];
        if ($name !== null && trim($name) !== '') {
            $p = $this->heuristic->nameLikePatterns($name);
            if ($p !== []) {
                $out['name_patterns'] = $p;
            }
        }
        if ($email !== null && trim($email) !== '') {
            $p = $this->heuristic->emailLikePatterns($email);
            if ($p !== []) {
                $out['email_patterns'] = $p;
            }
        }

        return $out;
    }
}
