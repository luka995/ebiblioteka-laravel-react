<?php

namespace App\Services\Queries;

use App\DTOs\Queries\NewsListQueryDTO;
use App\Models\News;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class NewsQueryService
{
    use AppliesTransliteratedSearch;

    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<News>
     */
    public function build(NewsListQueryDTO $query): Builder
    {
        $builder = News::query();

        if ($query->search !== null && trim($query->search) !== '') {
            $this->whereTransliterated($builder, 'title', trim($query->search));
        }

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'title' => 'news.title',
                'date' => 'news.date',
            ])
            ->defaults(new SortRule('date', 'desc'))
            ->apply($builder, $sort);
    }
}
