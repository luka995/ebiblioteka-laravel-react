<?php

namespace App\Services\Queries;

use App\DTOs\Queries\BookImportListQueryDTO;
use App\Models\BookImport;
use App\Models\Library;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class BookImportQueryService
{
    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<BookImport>
     */
    public function build(BookImportListQueryDTO $query, Library $library): Builder
    {
        $builder = BookImport::query()->with(['library', 'user'])->where('library_id', $library->id);
        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'created_at' => 'book_imports.created_at',
                'status' => 'book_imports.status',
                'total_rows' => 'book_imports.total_rows',
                'imported_rows' => 'book_imports.imported_rows',
                'failed_rows' => 'book_imports.failed_rows',
                'requested_by' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from users where users.id = book_imports.user_id) '.$direction
                ),
            ])
            ->defaults(new SortRule('created_at', 'desc'))
            ->apply($builder, $sort);
    }
}
