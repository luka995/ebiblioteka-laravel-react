<?php

namespace App\Services\Queries;

use App\DTOs\Queries\BarcodePrintJobListQueryDTO;
use App\Models\BarcodePrintJob;
use App\Models\Library;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class BarcodePrintJobQueryService
{
    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<BarcodePrintJob>
     */
    public function build(BarcodePrintJobListQueryDTO $query, Library $library): Builder
    {
        $builder = BarcodePrintJob::query()
            ->with(['library', 'user'])
            ->where('library_id', $library->id);

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'created_at' => 'barcode_print_jobs.created_at',
                'format' => 'barcode_print_jobs.format',
                'items_count' => 'barcode_print_jobs.items_count',
                'invalid_count' => 'barcode_print_jobs.invalid_count',
                'status' => 'barcode_print_jobs.status',
                'requested_by' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from users where users.id = barcode_print_jobs.user_id) '.$direction
                ),
            ])
            ->defaults(new SortRule('created_at', 'desc'))
            ->apply($builder, $sort);
    }
}
