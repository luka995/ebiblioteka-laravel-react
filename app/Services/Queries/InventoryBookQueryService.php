<?php

namespace App\Services\Queries;

use App\DTOs\Queries\InventoryBookListQueryDTO;
use App\Models\InventoryBook;
use App\Models\Library;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class InventoryBookQueryService
{
    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<InventoryBook>
     */
    public function build(InventoryBookListQueryDTO $query, Library $library): Builder
    {
        $builder = InventoryBook::query()
            ->with(['library', 'user'])
            ->where('library_id', $library->id);

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'created_at' => 'inventory_books.created_at',
                'status' => 'inventory_books.status',
                'rows_count' => 'inventory_books.rows_count',
                'requested_by' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from users where users.id = inventory_books.user_id) '.$direction
                ),
            ])
            ->defaults(new SortRule('created_at', 'desc'))
            ->apply($builder, $sort);
    }
}
