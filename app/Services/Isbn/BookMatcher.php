<?php

namespace App\Services\Isbn;

use App\Models\Book;
use App\Models\Library;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Support\Text;
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

    /**
     * Naslovi koji predstavljaju isti naslov: poklapa se naslov i najmanje
     * jedan autor. Ako autor nije prosledjen, poredi se samo naslov. Sluzi za
     * predlog ponovnog koriscenja naslova umesto kreiranja duplikata.
     *
     * @param  array<int, string>  $authors
     * @return Collection<int, Book>
     */
    public function duplicates(
        Library $library,
        ?string $title,
        array $authors = [],
        ?int $excludeBookId = null,
        int $limit = 10,
    ): Collection {
        $title = trim((string) $title);

        if ($title === '') {
            return collect();
        }

        // Za pretragu koristi samo osnovni naslov (bez podnaslova/odgovornosti),
        // da se poklopi i kada druga strana ima dodatni deo.
        $baseTitle = trim(preg_split('/\s*[:\/]\s*/u', $title)[0] ?? $title);
        $title = $baseTitle === '' ? $title : $baseTitle;

        $query = Book::query()
            ->where('library_id', $library->id)
            ->with(['library', 'authors', 'categoryPrimary', 'categorySecondary'])
            ->withCount('activeCopies');

        if ($excludeBookId !== null) {
            $query->whereKeyNot($excludeBookId);
        }

        $this->whereTransliterated($query, 'name', $title);

        $titleKey = $this->titleKey($title);

        if ($titleKey === '') {
            return collect();
        }

        $candidates = $query->limit(100)->get()
            ->filter(fn (Book $book): bool => $this->titleKey((string) $book->name) === $titleKey);

        $authorKeys = array_values(array_unique(array_filter(array_map(
            fn (string $author): string => $this->authorKey($author),
            $authors,
        ))));

        if ($authorKeys === []) {
            return $candidates->take($limit)->values();
        }

        return $candidates
            ->filter(function (Book $book) use ($authorKeys): bool {
                $bookKeys = $book->authors
                    ->map(fn ($author): string => $this->authorKey($author->name))
                    ->all();

                return array_intersect($authorKeys, $bookKeys) !== [];
            })
            ->take($limit)
            ->values();
    }

    /**
     * Kanonski kljuc naslova: transliteracija na latinicu, bez podnaslova i
     * odgovornosti, mala slova, bez dijakritika i interpunkcije.
     */
    private function titleKey(string $title): string
    {
        $latin = Text::lat(trim($title));
        $latin = preg_split('/\s*[:\/]\s*/u', $latin)[0] ?? $latin;
        $folded = strtr(mb_strtolower($latin, 'UTF-8'), [
            'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'd',
        ]);

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $folded) ?: [];
        $tokens = array_values(array_filter($tokens, fn (string $token): bool => $token !== ''));

        return implode(' ', $tokens);
    }

    /**
     * Kanonski kljuc autora: transliteracija na latinicu, mala slova, bez
     * dijakritika i interpunkcije, tokeni sortirani (neosetljivo na redosled).
     */
    private function authorKey(string $name): string
    {
        $latin = Text::lat(trim($name));
        $folded = strtr(mb_strtolower($latin, 'UTF-8'), [
            'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'd',
        ]);

        $tokens = preg_split('/[^\p{L}]+/u', $folded) ?: [];
        $tokens = array_values(array_filter($tokens, fn (string $token): bool => $token !== ''));
        sort($tokens);

        return implode(' ', $tokens);
    }
}
