<?php

namespace App\Services\Queries;

use App\DTOs\Queries\TagListQueryDTO;
use App\Models\Library;
use App\Models\Tag;
use App\Models\User;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class TagQueryService
{
    use AppliesTransliteratedSearch;

    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<Tag>
     */
    public function build(TagListQueryDTO $query, User $user, Library $library): Builder
    {
        $builder = Tag::query()
            ->with('library')
            ->withCount('users');

        if ($user->isSuperAdmin()) {
            $builder->where('library_id', $query->libraryId ?? $library->id);
        } else {
            $builder->where('library_id', $library->id);
        }

        $term = trim((string) ($query->search ?? ''));

        if ($term !== '') {
            $this->whereTransliterated($builder, 'name', $term);
        }

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'name' => 'tags.name',
                'library' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from libraries where libraries.id = tags.library_id) '.$direction
                ),
                'users_count' => 'users_count',
            ])
            ->defaults(new SortRule('name'))
            ->apply($builder, $sort);
    }
}
