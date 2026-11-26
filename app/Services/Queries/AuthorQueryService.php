<?php

namespace App\Services\Queries;

use App\DTOs\Queries\AuthorListQueryDTO;
use App\Models\Author;
use App\Models\Library;
use App\Models\User;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class AuthorQueryService
{
    use AppliesTransliteratedSearch;

    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<Author>
     */
    public function build(AuthorListQueryDTO $query, User $user, ?Library $active): Builder
    {
        $builder = Author::query()->with('library');

        if (! $user->isSuperAdmin()) {
            if ($active === null) {
                $builder->whereRaw('1 = 0');
            } else {
                $builder->where('library_id', $active->id);
            }
        } else {
            $libraryId = $query->libraryId ?? $active?->id;

            if ($libraryId !== null) {
                $builder->where('library_id', $libraryId);
            }
        }

        if ($query->search !== null && trim($query->search) !== '') {
            $this->whereTransliterated($builder, 'name', trim($query->search));
        }

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'name' => 'authors.name',
                'library' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from libraries where libraries.id = authors.library_id) '.$direction
                ),
            ])
            ->defaults(new SortRule('name'))
            ->apply($builder, $sort);
    }
}
