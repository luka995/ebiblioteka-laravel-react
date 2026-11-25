<?php

namespace App\Services\Queries;

use App\DTOs\Queries\BorrowListQueryDTO;
use App\Models\Borrow;
use App\Models\Library;
use App\Models\User;
use App\Queries\BorrowFilters;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class BorrowQueryService
{
    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<Borrow>
     */
    public function forUser(BorrowListQueryDTO $query, User $user): Builder
    {
        $builder = Borrow::query()
            ->with($this->relations())
            ->where('user_id', $user->id);

        $builder = (new BorrowFilters)->apply($builder, $query);

        return $this->applyOrdering($builder, $query, $query->status ?? 'active');
    }

    /**
     * @return Builder<Borrow>
     */
    public function forLibrary(BorrowListQueryDTO $query, Library $library): Builder
    {
        $builder = Borrow::query()
            ->with($this->relations())
            ->where('library_id', $library->id);

        $builder = (new BorrowFilters)->apply($builder, $query);

        return $this->applyOrdering($builder, $query, $query->status ?? 'active');
    }

    /**
     * @param  Builder<Borrow>  $query
     * @return Builder<Borrow>
     */
    private function applyOrdering(Builder $query, BorrowListQueryDTO $dto, string $status): Builder
    {
        $defaultSort = match ($status) {
            'history' => 'return_date',
            'active', 'overdue' => 'valid_till',
            default => 'created_at',
        };

        $defaultDirection = $status === 'history' ? 'desc' : ($status === 'active' || $status === 'overdue' ? 'asc' : 'desc');

        $sort = $this->sortParser->parse(
            $dto->sorting->sort,
            $dto->sorting->direction,
        );

        return (new QuerySortBuilder)
            ->allow([
                'user' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from users where users.id = borrows.user_id) '.$direction
                ),
                'title' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from books where books.id = borrows.book_id) '.$direction
                ),
                'barcode' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select barcode from book_copies where book_copies.id = borrows.book_copy_id) '.$direction
                ),
                'from_date' => 'borrows.from_date',
                'valid_till' => 'borrows.valid_till',
                'return_date' => 'borrows.return_date',
                'status' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    "case
                        when borrows.returned then 'returned'
                        when borrows.valid_till < now() then 'overdue'
                        else 'active'
                    end {$direction}"
                ),
                'created_at' => 'borrows.created_at',
            ])
            ->defaults(new SortRule($defaultSort, $defaultDirection))
            ->apply($query, $sort);
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'library.settings',
            'user',
            'book.authors',
            'bookCopy',
            'createdBy',
        ];
    }
}
