<?php

namespace App\Services\Queries;

use App\DTOs\Queries\ReservationListQueryDTO;
use App\Enums\ReservationStatus;
use App\Models\Library;
use App\Models\Reservation;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class ReservationQueryService
{
    use AppliesTransliteratedSearch;

    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<Reservation>
     */
    public function forUser(ReservationListQueryDTO $query, int $userId): Builder
    {
        $builder = Reservation::query()
            ->with(['book.authors', 'bookCopy', 'library'])
            ->where('user_id', $userId)
            ->where('status', ReservationStatus::Active)
            ->where('valid_till', '>', now());

        return $this->applySorting($builder, $query, 'valid_till', 'asc');
    }

    /**
     * @return Builder<Reservation>
     */
    public function forLibrary(ReservationListQueryDTO $query, Library $library): Builder
    {
        $status = trim((string) ($query->status ?? 'active'));

        $builder = Reservation::query()
            ->with(['book.authors', 'bookCopy', 'library', 'user'])
            ->where('library_id', $library->id);

        if ($query->dateFrom !== null && $query->dateFrom !== '') {
            $builder->whereDate('from_date', '>=', $query->dateFrom);
        }

        if ($query->dateTo !== null && $query->dateTo !== '') {
            $builder->whereDate('from_date', '<=', $query->dateTo);
        }

        if ($status === 'active') {
            $builder->where('status', ReservationStatus::Active)
                ->where('valid_till', '>', now());
        } elseif ($status === 'history') {
            $builder->where('status', '!=', ReservationStatus::Active);
        }

        if ($query->search !== null && trim($query->search) !== '') {
            $term = trim($query->search);

            $builder->where(function (Builder $group) use ($term): void {
                $group->whereHas('book', fn (Builder $book) => $this->whereTransliterated($book, 'name', $term))
                    ->orWhereHas('user', function (Builder $user) use ($term): void {
                        $this->whereTransliterated($user, 'first_name', $term);
                        $this->whereTransliterated($user, 'last_name', $term, 'or');
                        $this->whereTransliterated($user, 'name', $term, 'or');
                        $user->orWhere('bar_code', 'like', '%'.$term.'%');
                    })
                    ->orWhereHas('bookCopy', fn (Builder $copy) => $copy->where('barcode', 'like', '%'.$term.'%'));
            });
        }

        return $this->applySorting(
            $builder,
            $query,
            $status === 'active' ? 'valid_till' : 'created_at',
            $status === 'active' ? 'asc' : 'desc',
        );
    }

    /**
     * @param  Builder<Reservation>  $query
     * @return Builder<Reservation>
     */
    private function applySorting(
        Builder $query,
        ReservationListQueryDTO $dto,
        string $defaultSort,
        string $defaultDirection,
    ): Builder {
        $sort = $this->sortParser->parse(
            $dto->sorting->sort,
            $dto->sorting->direction,
        );

        return (new QuerySortBuilder)
            ->allow([
                'user' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from users where users.id = reservations.user_id) '.$direction
                ),
                'title' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from books where books.id = reservations.book_id) '.$direction
                ),
                'barcode' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select barcode from book_copies where book_copies.id = reservations.book_copy_id) '.$direction
                ),
                'library' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from libraries where libraries.id = reservations.library_id) '.$direction
                ),
                'valid_till' => 'reservations.valid_till',
                'status' => 'reservations.status',
                'updated_at' => 'reservations.updated_at',
                'created_at' => 'reservations.created_at',
            ])
            ->defaults(new SortRule($defaultSort, $defaultDirection))
            ->apply($query, $sort);
    }
}
