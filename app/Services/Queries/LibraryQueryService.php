<?php

namespace App\Services\Queries;

use App\DTOs\Queries\LibraryListQueryDTO;
use App\Enums\LibraryRole;
use App\Models\Library;
use App\Models\User;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class LibraryQueryService
{
    use AppliesTransliteratedSearch;

    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<Library>
     */
    public function build(LibraryListQueryDTO $query, User $user): Builder
    {
        $builder = Library::query()
            ->with(['place.region'])
            ->when(! $user->isSuperAdmin(), function (Builder $builder) use ($user): void {
                $builder->whereIn(
                    'id',
                    $user->libraries()
                        ->wherePivot('role', LibraryRole::LibraryAdmin->value)
                        ->pluck('libraries.id')
                );
            })
            ->when(! $query->deleted, fn (Builder $builder) => $builder->where('deleted', false));

        if ($query->search !== null && trim($query->search) !== '') {
            $term = trim($query->search);

            $builder->where(function (Builder $group) use ($term): void {
                $this->whereTransliterated($group, 'name', $term);
                $this->whereTransliterated($group, 'address', $term, 'or');
                $group->orWhereHas('place', fn (Builder $place) => $this->whereTransliterated($place, 'name', $term));
            });
        }

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'name' => 'libraries.name',
                'place' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from places where places.id = libraries.place_id) '.$direction
                ),
                'region' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select regions.name from regions join places on places.region_id = regions.id where places.id = libraries.place_id) '.$direction
                ),
                'address' => 'libraries.address',
                'work_time' => 'libraries.work_time',
                'created_at' => 'libraries.created_at',
            ])
            ->defaults(new SortRule('created_at', 'desc'))
            ->apply($builder, $sort);
    }
}
