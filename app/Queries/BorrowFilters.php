<?php

namespace App\Queries;

use App\DTOs\Queries\BorrowListQueryDTO;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Support\BarCode;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filteri liste pozajmica za aktivnu biblioteku i sopstvene pozajmice.
 */
class BorrowFilters
{
    use AppliesTransliteratedSearch;

    public function apply(Builder $query, BorrowListQueryDTO $filters): Builder
    {
        $status = trim((string) ($filters->status ?? 'active'));

        if ($status === 'active') {
            $query->where('returned', false);
        } elseif ($status === 'history') {
            $query->where('returned', true);
        } elseif ($status === 'overdue') {
            $query->where('returned', false)->where('valid_till', '<', now());
        }

        if ($filters->overdue) {
            $query->where('returned', false)->where('valid_till', '<', now());
        }

        if ($filters->dateFrom !== null && $filters->dateFrom !== '') {
            $query->whereDate('from_date', '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== null && $filters->dateTo !== '') {
            $query->whereDate('from_date', '<=', $filters->dateTo);
        }

        $term = trim((string) ($filters->search ?? ''));

        if ($term !== '') {
            $barcode = BarCode::normalizeSearchInput($term);

            $query->where(function (Builder $group) use ($term, $barcode): void {
                $group->whereHas('user', function (Builder $user) use ($term): void {
                    $this->whereTransliterated($user, 'first_name', $term, 'or');
                    $this->whereTransliterated($user, 'last_name', $term, 'or');
                    $this->whereTransliterated($user, 'name', $term, 'or');
                    $this->whereTransliterated($user, 'username', $term, 'or');
                    $user->orWhere('bar_code', 'like', '%'.$barcode.'%');
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
