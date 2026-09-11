<?php

namespace App\Queries;

use App\Queries\Concerns\AppliesTransliteratedSearch;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pretraga tagova po nazivu (oba pisma) i pripadnosti biblioteci.
 */
class TagFilters
{
    use AppliesTransliteratedSearch;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $term = trim((string) ($filters['search'] ?? ''));

        if ($term !== '') {
            $this->whereTransliterated($query, 'name', $term);
        }

        if (! empty($filters['library_id'])) {
            $query->where('library_id', (int) $filters['library_id']);
        }

        return $query;
    }
}
