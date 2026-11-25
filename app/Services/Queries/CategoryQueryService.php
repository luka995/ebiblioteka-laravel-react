<?php

namespace App\Services\Queries;

use App\DTOs\Queries\CategoryListQueryDTO;
use App\Models\Category;
use App\Models\Library;
use App\Models\User;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class CategoryQueryService
{
    use AppliesTransliteratedSearch;

    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<Category>
     */
    public function build(CategoryListQueryDTO $query, User $user, Library $library): Builder
    {
        $builder = Category::query()
            ->with('parent', 'library')
            ->withCount('children');

        if (! $user->isSuperAdmin()) {
            $builder->where('library_id', $library->id);
        } else {
            $builder->where('library_id', $query->libraryId ?? $library->id);
        }

        if ($query->parentId !== null) {
            $builder->where('parent_id', $query->parentId);
        }

        if ($query->search !== null && trim($query->search) !== '') {
            $this->whereTransliterated($builder, 'name', trim($query->search));
        }

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'name' => 'categories.name',
                'library' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select name from libraries where libraries.id = categories.library_id) '.$direction
                ),
                'children_count' => 'children_count',
            ])
            ->defaults(new SortRule('name'))
            ->apply($builder, $sort);
    }
}
