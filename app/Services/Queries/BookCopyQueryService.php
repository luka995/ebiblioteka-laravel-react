<?php

namespace App\Services\Queries;

use App\DTOs\Queries\BookCopyListQueryDTO;
use App\Models\BookCopy;
use App\Models\Library;
use App\Queries\BookCopyFilters;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class BookCopyQueryService
{
    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<BookCopy>
     */
    public function index(BookCopyListQueryDTO $query, Library $library): Builder
    {
        $builder = BookCopy::query()->with(['book', 'activeWriteOff']);
        $builder->where('library_id', $library->id);

        $filters = $query->filters;

        if ($query->bookId !== null) {
            $filters['book_id'] = $query->bookId;
        }

        $builder = (new BookCopyFilters)->apply($builder, $filters);

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'order_number' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    'CAST(book_copies.order_number AS BIGINT) '.$direction
                ),
                'barcode' => 'book_copies.barcode',
                'book' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from books where books.id = book_copies.book_id) '.$direction
                ),
                'isbn' => 'book_copies.isbn',
                'status' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    "case
                        when exists(select 1 from book_copy_write_offs w where w.book_copy_id = book_copies.id and w.cancelled_at is null) then 'written_off'
                        when book_copies.borrowed then 'borrowed'
                        when book_copies.reserved then 'reserved'
                        when book_copies.rec_error then 'record_error'
                        else 'available'
                    end {$direction}"
                ),
            ])
            ->defaults(new SortRule('order_number'))
            ->apply($builder, $sort);
    }

    /**
     * @return Builder<BookCopy>
     */
    public function archive(BookCopyListQueryDTO $query, Library $library): Builder
    {
        $builder = BookCopy::onlyTrashed()->with(['book', 'activeWriteOff']);
        $builder->where('library_id', $library->id);

        if ($query->bookId !== null) {
            $builder->where('book_id', $query->bookId);
        }

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'order_number' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    'CAST(book_copies.order_number AS BIGINT) '.$direction
                ),
                'book' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from books where books.id = book_copies.book_id) '.$direction
                ),
                'barcode' => 'book_copies.barcode',
                'deleted_at' => 'book_copies.deleted_at',
            ])
            ->defaults(new SortRule('deleted_at', 'desc'))
            ->apply($builder, $sort);
    }
}
