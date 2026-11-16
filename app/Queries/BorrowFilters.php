<?php

namespace App\Queries;

use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Support\BarCode;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filteri liste pozajmica za aktivnu biblioteku i sopstvene pozajmice.
 */
class BorrowFilters
{
    use AppliesTransliteratedSearch;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $status = trim((string) ($filters['status'] ?? ''));

        if ($status === 'active') {
            $query->where('returned', false);
        } elseif ($status === 'history') {
            $query->where('returned', true);
        } elseif ($status === 'overdue') {
            $query->where('returned', false)->where('valid_till', '<', now());
        }

        if (! empty($filters['overdue'])) {
            $query->where('returned', false)->where('valid_till', '<', now());
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('from_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('from_date', '<=', $filters['date_to']);
        }

        $term = trim((string) ($filters['search'] ?? ''));

        if ($term !== '') {
            $barcode = BarCode::normalizeSearchInput($term);

            $query->where(function (Builder $group) use ($term, $barcode): void {
                $group->whereHas('user', function (Builder $user) use ($term): void {
                    $this->whereTransliterated($user, 'first_name', $term, 'or');
                    $this->whereTransliterated($user, 'last_name', $term, 'or');
                    $this->whereTransliterated($user, 'name', $term, 'or');
                    $user->orWhere('username', 'like', '%'.$term.'%')
                        ->orWhere('bar_code', 'like', '%'.$barcode.'%');
                })
                    ->orWhereHas('book', function (Builder $book) use ($term): void {
                        $this->whereTransliterated($book, 'name', $term, 'or');
                    })
                    ->orWhereHas('bookCopy', function (Builder $copy) use ($barcode): void {
                        $copy->where('barcode', 'like', '%'.$barcode.'%')
                            ->orWhere('order_number', 'like', '%'.$barcode.'%');
                    });
            });
        }

        return $query;
    }
}
