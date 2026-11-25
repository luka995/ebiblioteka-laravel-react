<?php

namespace App\Services\Queries;

use App\DTOs\Queries\SchoolYearListQueryDTO;
use App\Models\SchoolYear;
use App\Services\SchoolYearService;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class SchoolYearQueryService
{
    public function __construct(
        private readonly SortParser $sortParser,
        private readonly SchoolYearService $years,
    ) {}

    /**
     * @return Builder<SchoolYear>
     */
    public function build(SchoolYearListQueryDTO $query): Builder
    {
        $builder = SchoolYear::query()
            ->with('createdBy')
            ->withCount('classes');

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'year' => 'school_years.year',
                'starts_on' => 'school_years.starts_on',
                'ends_on' => 'school_years.ends_on',
                'classes_count' => 'classes_count',
                'is_active' => function (Builder $query, string $direction): void {
                    $query->orderByRaw(
                        'case when school_years.id = ? then 0 else 1 end '.$direction,
                        [$this->years->activeId() ?? 0],
                    );
                },
            ])
            ->defaults(new SortRule('year', 'desc'))
            ->apply($builder, $sort);
    }
}
