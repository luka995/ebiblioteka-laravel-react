<?php

namespace App\Queries;

use App\Queries\Concerns\AppliesTransliteratedSearch;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pretraga biblioteka po nazivu, adresi i nazivu mesta, u oba pisma.
 */
class LibraryFilters
{
    use AppliesTransliteratedSearch;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $term = trim((string) ($filters['search'] ?? ''));

        if ($term === '') {
            return $query;
        }

        $query->where(function (Builder $group) use ($term) {
            $this->whereTransliterated($group, 'name', $term);
            $this->whereTransliterated($group, 'address', $term, 'or');
            $group->orWhereHas('place', fn (Builder $place) => $this->whereTransliterated($place, 'name', $term));
        });

        return $query;
    }
}
