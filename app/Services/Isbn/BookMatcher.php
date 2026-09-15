<?php

namespace App\Services\Isbn;

use App\Models\Book;
use App\Models\Library;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use Illuminate\Support\Collection;

/**
 * Pronalazi postojeće naslove u biblioteci koji odgovaraju datom naslovu i
 * autorima (za ponovno koriscenje umesto kreiranja duplikata).
 */
class BookMatcher
{
    use AppliesTransliteratedSearch;

    /**
     * @param  array<int, string>  $authors
     * @return Collection<int, Book>
     */
    public function candidates(Library $library, ?string $title, array $authors = [], int $limit = 10): Collection
    {
        $title = trim((string) $title);

        if ($title === '') {
            return collect();
        }

        $query = Book::query()
            ->where('library_id', $library->id)
            ->with(['authors', 'categoryPrimary', 'categorySecondary'])
            ->withCount('activeCopies');

        $this->whereTransliterated($query, 'name', $title);

        $candidates = $query->limit(50)->get();

        $normalizedAuthors = array_map(
            fn (string $author): string => mb_strtolower(trim($author)),
            array_filter($authors),
        );

        return $candidates
            ->when($normalizedAuthors !== [], fn (Collection $items): Collection => $items->sortByDesc(
                function (Book $book) use ($normalizedAuthors): int {
                    $bookAuthors = $book->authors
                        ->map(fn ($author): string => mb_strtolower(trim($author->name)))
                        ->all();

                    return count(array_intersect($normalizedAuthors, $bookAuthors));
                }
            ))
            ->take($limit)
            ->values();
    }
}
