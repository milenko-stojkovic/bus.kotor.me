<?php

namespace App\Services\AdminPanel\Agency;

use App\Models\User;
use App\Services\AdminPanel\Reservation\AdminReservationSearchHeuristic;
use Illuminate\Database\Eloquent\Builder;

final class AdminAgencySearchService
{
    public function __construct(
        private AdminReservationSearchHeuristic $heuristic,
    ) {}

    /**
     * @param  Builder<User>  $query
     */
    public function applySearch(Builder $query, ?string $raw): void
    {
        $term = trim((string) $raw);
        if ($term === '') {
            return;
        }

        $namePatterns = $this->heuristic->nameLikePatterns($term);
        $emailPatterns = $this->heuristic->emailLikePatterns($term);

        if ($namePatterns === [] && $emailPatterns === []) {
            return;
        }

        $query->where(function (Builder $q) use ($namePatterns, $emailPatterns): void {
            foreach ($namePatterns as $pattern) {
                $q->orWhere('name', 'like', $pattern);
                $core = trim($pattern, '%');
                if ($core !== '') {
                    $q->orWhereRaw("REPLACE(LOWER(name), ' ', '') LIKE ?", ['%'.$core.'%']);
                }
            }
            foreach ($emailPatterns as $pattern) {
                $q->orWhere('email', 'like', $pattern);
            }
        });
    }
}
