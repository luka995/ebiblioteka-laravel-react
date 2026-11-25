<?php

namespace App\Services;

use App\Models\BookCopy;
use App\Models\Borrow;
use App\Models\Library;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Agregirani pokazatelji aktivne biblioteke za dashboard osoblja.
 */
class DashboardStatsService
{
    /**
     * @return array{
     *     members_count: int,
     *     books_count: int,
     *     active_borrows_count: int,
     *     overdue_borrows_count: int
     * }
     */
    public function forLibrary(Library $library): array
    {
        $overdueBorrows = Borrow::query()
            ->where('library_id', $library->id)
            ->where('returned', false)
            ->where('valid_till', '<', now())
            ->count();

        return [
            'members_count' => User::query()
                ->whereHas('libraries', fn (Builder $query) => $query->whereKey($library->id))
                ->count(),
            'books_count' => BookCopy::query()
                ->where('library_id', $library->id)
                ->whereDoesntHave('writeOffs', fn (Builder $query) => $query->whereNull('cancelled_at'))
                ->count(),
            'active_borrows_count' => Borrow::query()
                ->where('library_id', $library->id)
                ->where('returned', false)
                ->count(),
            'overdue_borrows_count' => $overdueBorrows,
        ];
    }
}
