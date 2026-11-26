<?php

namespace App\Services\Queries;

use App\DTOs\Queries\BookImportRowListQueryDTO;
use App\Models\BookImport;
use App\Models\BookImportRow;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class BookImportRowQueryService
{
    use AppliesTransliteratedSearch;

    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<BookImportRow>
     */
    public function build(BookImportRowListQueryDTO $query, BookImport $import): Builder
    {
        $builder = BookImportRow::query()
            ->with('bookCopy')
            ->where('book_import_id', $import->id);

        if ($query->status !== null) {
            $builder->where('status', $query->status);
        }

        if ($query->failureCode !== null) {
            $builder->where('failure_code', $query->failureCode);
        }

        if ($query->attemptMode !== null) {
            $builder->where('attempt_mode', $query->attemptMode);
        }

        $search = trim((string) $query->search);

        if ($search !== '') {
            $this->applySearch($builder, $search);
        }

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'row_number' => 'book_import_rows.row_number',
                'status' => 'book_import_rows.status',
                'attempts' => 'book_import_rows.attempts',
                'created_at' => 'book_import_rows.created_at',
            ])
            ->defaults(new SortRule('row_number'))
            ->apply($builder, $sort);
    }

    /**
     * @return array{matching_pending_rows: int, matching_failed_rows: int}
     */
    public function matchingCounts(Builder $builder): array
    {
        $counts = (clone $builder)->setEagerLoads([])->reorder()->selectRaw(
            'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS matching_pending_rows,
            COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS matching_failed_rows',
            ['pending', 'failed'],
        )->first();

        return [
            'matching_pending_rows' => (int) $counts->matching_pending_rows,
            'matching_failed_rows' => (int) $counts->matching_failed_rows,
        ];
    }

    /**
     * @return list<int>
     */
    public function matchingIds(
        BookImportRowListQueryDTO $query,
        BookImport $import,
        string $requiredStatus,
    ): array {
        return $this->build($query, $import)
            ->where('status', $requiredStatus)
            ->lockForUpdate()
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function applySearch(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        $query->where(function (Builder $search) use ($term, $like, $operator): void {
            $search->whereRaw('CAST(book_import_rows.row_number AS TEXT) '.$operator.' ?', [$like])
                ->orWhere('book_import_rows.isbn', $operator, $like)
                ->orWhere('book_import_rows.inventory_number', $operator, $like)
                ->orWhereHas('bookCopy', fn (Builder $copy) => $copy->where('order_number', $operator, $like));

            $this->whereTransliterated($search, 'book_import_rows.title', $term, 'or');
        });
    }
}
