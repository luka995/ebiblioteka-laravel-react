<?php

namespace App\Services\Isbn;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use Illuminate\Support\Collection;

/**
 * ISBN lookup tok: prvo interna baza (book_copies.isbn), pa eksterni izvori.
 *
 * Ako je ISBN nadjen u bazi, ne poziva se API; vraca se povezani naslov i
 * postojeće kopije. Ako nije, preuzimaju se metapodaci i traze se kandidati
 * (postojeći naslovi) za ponovno koriscenje.
 */
class IsbnLookupService
{
    public function __construct(
        private readonly IsbnNormalizer $normalizer,
        private readonly IsbnMetadataService $metadata,
        private readonly BookMatcher $matcher,
    ) {}

    /**
     * @return array{
     *     source: string|null,
     *     isbn: string,
     *     metadata: BookMetadata|null,
     *     book: Book|null,
     *     existing_copies: Collection<int, BookCopy>,
     *     matches: Collection<int, Book>
     * }|null
     */
    public function search(Library $library, string $isbn): ?array
    {
        $normalized = $this->normalizer->normalize($isbn);

        if ($normalized === '') {
            return null;
        }

        $forms = $this->normalizer->forms($isbn);

        if ($forms !== []) {
            $copies = BookCopy::query()
                ->where('library_id', $library->id)
                ->whereIn('isbn', $forms)
                ->with(['book.authors', 'book.categoryPrimary', 'book.categorySecondary'])
                ->orderByRaw('CAST(order_number AS BIGINT)')
                ->orderBy('id')
                ->get();

            if ($copies->isNotEmpty()) {
                return [
                    'source' => 'database',
                    'isbn' => $normalized,
                    'metadata' => null,
                    'book' => $copies->first()->book,
                    'existing_copies' => $copies,
                    'matches' => collect(),
                ];
            }
        }

        $metadata = $this->metadata->lookup($isbn);

        if ($metadata === null) {
            return [
                'source' => null,
                'isbn' => $normalized,
                'metadata' => null,
                'book' => null,
                'existing_copies' => collect(),
                'matches' => collect(),
            ];
        }

        return [
            'source' => $metadata->source,
            'isbn' => $normalized,
            'metadata' => $metadata,
            'book' => null,
            'existing_copies' => collect(),
            'matches' => $this->matcher->duplicates($library, $metadata->title, $metadata->authors),
        ];
    }
}
