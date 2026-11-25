<?php

namespace App\Services\Queries;

use App\DTOs\Queries\BookListQueryDTO;
use App\Models\Book;
use App\Models\Library;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\Sorting\QuerySortBuilder;
use App\Services\Sorting\SortParser;
use App\Services\Sorting\SortRule;
use Illuminate\Database\Eloquent\Builder;

final class BookQueryService
{
    use AppliesTransliteratedSearch;

    public function __construct(private readonly SortParser $sortParser) {}

    /**
     * @return Builder<Book>
     */
    public function build(BookListQueryDTO $query, Library $library): Builder
    {
        $builder = Book::query()
            ->with(['library', 'categoryPrimary', 'categorySecondary', 'authors']);
        $this->withAvailabilityCounts($builder);

        $builder->where('library_id', $library->id);

        if ($query->search !== null && trim($query->search) !== '') {
            $this->whereTransliterated($builder, 'name', trim($query->search));
        }

        if ($query->isbn !== null && trim($query->isbn) !== '') {
            $term = trim($query->isbn);
            $builder->whereHas('activeCopies', fn (Builder $copy) => $copy->where('isbn', 'like', '%'.$term.'%'));
        }

        if ($query->categoryId !== null) {
            $categoryId = $query->categoryId;
            $builder->where(function (Builder $group) use ($categoryId): void {
                $group->where('category_primary_id', $categoryId)
                    ->orWhere('category_secondary_id', $categoryId);
            });
        }

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'name' => 'books.name',
                'authors' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select min(authors.name) from authors join book_authors on book_authors.author_id = authors.id where book_authors.book_id = books.id) '.$direction
                ),
                'category' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select categories.name from categories where categories.id = books.category_primary_id) '.$direction
                ),
                'copies_count' => 'copies_count',
                'available_count' => 'available_count',
            ])
            ->defaults(new SortRule('name'))
            ->apply($builder, $sort);
    }

    /**
     * @return Builder<Book>
     */
    public function archive(BookListQueryDTO $query, Library $library): Builder
    {
        $builder = Book::onlyTrashed()
            ->with(['library', 'categoryPrimary', 'categorySecondary', 'authors']);
        $this->withAvailabilityCounts($builder);

        $builder->where('library_id', $library->id);

        $sort = $this->sortParser->parse($query->sorting->sort, $query->sorting->direction);

        return (new QuerySortBuilder)
            ->allow([
                'name' => 'books.name',
                'authors' => fn (Builder $query, string $direction) => $query->orderByRaw(
                    '(select min(authors.name) from authors join book_authors on book_authors.author_id = authors.id where book_authors.book_id = books.id) '.$direction
                ),
                'deleted_at' => 'books.deleted_at',
            ])
            ->defaults(new SortRule('deleted_at', 'desc'))
            ->apply($builder, $sort);
    }

    /**
     * @param  Builder<Book>  $query
     */
    private function withAvailabilityCounts(Builder $query): void
    {
        $query->withCount([
            'activeCopies as copies_count' => fn (Builder $sub) => $sub
                ->whereDoesntHave('writeOffs', fn ($writeOff) => $writeOff->whereNull('cancelled_at')),
            'activeCopies as available_count' => fn (Builder $sub) => $sub
                ->where('borrowed', false)
                ->where('reserved', false)
                ->where('rec_error', false)
                ->whereDoesntHave('writeOffs', fn ($writeOff) => $writeOff->whereNull('cancelled_at')),
        ]);
    }
}
