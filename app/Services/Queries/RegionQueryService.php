<?php

namespace App\Services\Queries;

use App\DTOs\Queries\RegionListQueryDTO;
use App\Models\Region;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class RegionQueryService
{
    use AppliesTransliteratedSearch;

    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<Region>
     */
    public function build(RegionListQueryDTO $query): Builder
    {
        $builder = Region::query()->withCount('places');

        if ($query->search !== null && trim($query->search) !== '') {
            $this->whereTransliterated($builder, 'name', trim($query->search));
        }

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'name' => 'regions.name',
                'places_count' => 'places_count',
            ])
            ->defaults(new SortRule('name'))
            ->apply($builder, $sort);
    }
}
