<?php

namespace App\Services\Queries;

use App\DTOs\Queries\PlaceListQueryDTO;
use App\Models\Place;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class PlaceQueryService
{
    use AppliesTransliteratedSearch;

    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<Place>
     */
    public function build(PlaceListQueryDTO $query): Builder
    {
        $builder = Place::query()->with('region');

        if ($query->regionId !== null) {
            $builder->where('region_id', $query->regionId);
        }

        if ($query->search !== null && trim($query->search) !== '') {
            $this->whereTransliterated($builder, 'name', trim($query->search));
        }

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'name' => 'places.name',
                'region' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from regions where regions.id = places.region_id) '.$direction
                ),
            ])
            ->defaults(new SortRule('name'))
            ->apply($builder, $sort);
    }
}
