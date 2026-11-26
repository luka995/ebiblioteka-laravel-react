<?php

namespace App\Services\BookImports;

use App\DTOs\BookImportBookResultDTO;
use App\DTOs\BookImportResolutionDTO;
use App\DTOs\ExcelImportHeaderMapDTO;
use App\Enums\BookImportFailureEnum;
use App\Enums\IsbnLookupStatusEnum;
use App\Exceptions\BookImportRowFailureException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookImport;
use App\Models\BookImportRow;
use App\Models\Library;
use App\Services\BookService;
use App\Services\Catalog\CobissCategoryMapper;
use App\Services\CategoryResolverService;
use App\Services\Isbn\BookMatcher;
use App\Services\Isbn\IsbnMetadataService;
use App\Services\Isbn\IsbnNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use OpenSpout\Common\Entity\Cell;

class BookImportBookResolver
{
    /** @var array<int, array<string, int>> */
    private array $categoryIds = [];

    public function __construct(
        private readonly IsbnNormalizer $normalizer,
        private readonly IsbnMetadataService $metadata,
        private readonly BookMatcher $matcher,
        private readonly BookService $books,
        private readonly CobissCategoryMapper $cobissCategories,
        private readonly CategoryResolverService $categories,
        private readonly BookImportHeaderMapper $headers,
        private readonly BookImportRowPayloadMapper $rows,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function input(BookImportRow $row): array
    {
        return $this->normalize(Arr::only(
            array_replace($row->raw_payload, $row->input_overrides ?? []),
            $this->headers->fields(),
        ), $row->row_number);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function resolve(BookImport $import, BookImportRow $row, array $input): BookImportResolutionDTO
    {
        $isbn = $this->normalizer->normalize($input['isbn'] ?? '');

        if ($isbn === '') {
            throw new BookImportRowFailureException(BookImportFailureEnum::MissingIsbn);
        }

        if (! $this->normalizer->isValid($isbn)) {
            throw new BookImportRowFailureException(BookImportFailureEnum::InvalidIsbn);
        }

        $payload = array_replace(array_fill_keys($this->headers->fields(), null), ['authors' => []], $input, ['isbn' => $isbn]);
        $sources = [];

        foreach ($payload as $field => $value) {
            $sources[$field] = array_key_exists($field, $row->input_overrides ?? [])
                ? 'preview_edit' : (filled($value) ? 'excel' : 'default');
        }
        $inputSources = $sources;

        $local = BookCopy::query()->where('library_id', $import->library_id)
            ->whereIn('isbn', $this->normalizer->forms($isbn))
            ->whereHas('book', fn ($query) => $query->whereNull('books.deleted_at'))
            ->with('book.authors')->orderBy('id')->first();

        if ($local !== null) {
            return new BookImportResolutionDTO($payload, $sources, ['provider' => 'database', 'status' => 'found'], $local->book, $input, $inputSources);
        }

        $result = $this->metadata->lookupForImport($isbn, $import->id);

        if ($result->status !== IsbnLookupStatusEnum::Found) {
            throw new BookImportRowFailureException(
                $result->status === IsbnLookupStatusEnum::NotFound
                    ? BookImportFailureEnum::IsbnNotFound : BookImportFailureEnum::LookupFailed,
                diagnostics: $result->diagnostics,
            );
        }

        $metadata = $result->metadata;
        $diagnostics = $result->diagnostics;

        foreach ([
            'title' => $metadata->title, 'authors' => $metadata->authors,
            'publisher' => $metadata->publisher, 'publish_place' => $metadata->publishPlace,
            'publish_year' => $metadata->publishYear, 'num_of_pages' => $metadata->pages,
            'dimension' => $metadata->dimensions, 'description' => $metadata->description,
            'cover_url' => $metadata->coverUrl, 'category' => $metadata->category, 'udk' => $metadata->udk,
        ] as $field => $value) {
            if (! filled($value)) {
                continue;
            }

            try {
                $normalized = $this->normalize([$field => $value], $row->row_number);

                if (filled($normalized[$field] ?? null)) {
                    $payload[$field] = $normalized[$field];
                    $sources[$field] = 'cobiss';
                }
            } catch (BookImportRowFailureException) {
                $diagnostics['invalid_fields'][] = $field;
            }
        }

        if (isset($diagnostics['invalid_fields'])) {
            Log::channel(config('book_imports.log_channel'))->warning('book_import.invalid_metadata_fields', [
                'book_import_id' => $import->id, 'row_id' => $row->id, 'fields' => $diagnostics['invalid_fields'],
            ]);
        }

        if (! filled($payload['title'])) {
            throw new BookImportRowFailureException(BookImportFailureEnum::MissingTitle, diagnostics: $diagnostics);
        }

        return new BookImportResolutionDTO($payload, $sources, $diagnostics, input: $input, inputSources: $inputSources);
    }

    public function persist(Library $library, BookImportResolutionDTO $resolution): BookImportBookResultDTO
    {
        $book = $this->findReusableBook($library, $resolution);

        if ($resolution->existingBook !== null && $book === null) {
            throw new BookImportRowFailureException(BookImportFailureEnum::UnexpectedError, diagnostics: ['reason' => 'existing_book_missing']);
        }

        if ($book !== null) {
            return $this->persistExistingBook($book, $library, $resolution);
        }

        return $this->createBook($library, $resolution);
    }

    private function findReusableBook(Library $library, BookImportResolutionDTO $resolution): ?Book
    {
        if ($resolution->existingBook !== null) {
            return Book::where('library_id', $library->id)->find($resolution->existingBook->id);
        }

        return $this->matcher->duplicates(
            $library,
            $resolution->payload['title'],
            $resolution->payload['authors'],
        )->first();
    }

    private function persistExistingBook(
        Book $book,
        Library $library,
        BookImportResolutionDTO $resolution,
    ): BookImportBookResultDTO {
        $book->loadMissing(['authors', 'categoryPrimary', 'categorySecondary']);
        $updates = [];
        $sources = $resolution->sources;

        $this->fillMissingScalarFields($book, $resolution->payload, $updates, $sources);
        $this->fillMissingAuthors($book, $resolution->payload, $updates, $sources);
        $this->fillMissingPrimaryCategory($book, $library, $resolution, $updates, $sources);
        $this->fillMissingSecondaryCategory($book, $library, $resolution, $updates, $sources);

        if ($updates !== []) {
            $book = $this->books->update($book, $updates);
            $book->refresh()->load(['authors', 'categoryPrimary', 'categorySecondary']);
        }

        return new BookImportBookResultDTO($book, $sources);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $updates
     * @param  array<string, string>  $sources
     */
    private function fillMissingScalarFields(Book $book, array $payload, array &$updates, array &$sources): void
    {
        foreach ([
            'name' => 'title',
            'description' => 'description',
            'cover_url' => 'cover_url',
        ] as $attribute => $field) {
            $current = $book->getAttribute($attribute);
            $candidate = $payload[$field];

            if (! filled($current) && filled($candidate)) {
                $updates[$attribute] = $candidate;

                continue;
            }

            $sources[$field] = filled($current) ? 'existing_book' : 'default';
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $updates
     * @param  array<string, string>  $sources
     */
    private function fillMissingAuthors(Book $book, array $payload, array &$updates, array &$sources): void
    {
        if ($book->authors->isEmpty() && filled($payload['authors'])) {
            $updates['authors'] = $payload['authors'];

            return;
        }

        $sources['authors'] = $book->authors->isNotEmpty() ? 'existing_book' : 'default';
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  array<string, string>  $sources
     */
    private function fillMissingPrimaryCategory(
        Book $book,
        Library $library,
        BookImportResolutionDTO $resolution,
        array &$updates,
        array &$sources,
    ): void {
        if ($book->category_primary_id !== null) {
            $sources['category'] = 'existing_book';

            return;
        }

        [$categoryId, $source] = $this->primaryCategory($library, $resolution);

        if ($categoryId !== null) {
            $updates['category_primary_id'] = $categoryId;
        }

        $sources['category'] = $source;
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  array<string, string>  $sources
     */
    private function fillMissingSecondaryCategory(
        Book $book,
        Library $library,
        BookImportResolutionDTO $resolution,
        array &$updates,
        array &$sources,
    ): void {
        if ($book->category_secondary_id !== null) {
            $sources['category_secondary'] = 'existing_book';

            return;
        }

        [$categoryId, $source] = $this->secondaryCategory($library, $resolution);

        if ($categoryId !== null) {
            $updates['category_secondary_id'] = $categoryId;
        }

        $sources['category_secondary'] = $source;
    }

    private function createBook(Library $library, BookImportResolutionDTO $resolution): BookImportBookResultDTO
    {
        $payload = $resolution->payload;
        $sources = $resolution->sources;
        [$primary, $sources['category']] = $this->primaryCategory($library, $resolution);
        [$secondary, $sources['category_secondary']] = $this->secondaryCategory($library, $resolution);

        $book = $this->books->create([
            'library_id' => $library->id,
            'name' => $payload['title'],
            'authors' => $payload['authors'],
            'description' => $payload['description'],
            'cover_url' => $payload['cover_url'],
            'category_primary_id' => $primary,
            'category_secondary_id' => $secondary,
        ]);

        return new BookImportBookResultDTO($book, $sources);
    }

    /**
     * @return array{?int, string}
     */
    private function primaryCategory(Library $library, BookImportResolutionDTO $resolution): array
    {
        $payload = $resolution->payload;
        $sources = $resolution->sources;

        if ($sources['category'] === 'cobiss' || $sources['udk'] === 'cobiss') {
            $categoryId = $this->cobissCategories->categoryId(
                $library,
                $sources['category'] === 'cobiss' ? $payload['category'] : null,
                $sources['udk'] === 'cobiss' ? $payload['udk'] : null,
            );

            if ($categoryId !== null) {
                return [$categoryId, 'cobiss'];
            }
        }

        $categoryId = $this->category($library, $resolution->input['category'] ?? null);

        if ($categoryId !== null) {
            return [$categoryId, $resolution->inputSources['category'] ?? 'default'];
        }

        if (filled($payload['udk'])) {
            $categoryId = $this->cobissCategories->categoryId($library, null, $payload['udk']);

            if ($categoryId !== null) {
                return [$categoryId, $sources['udk']];
            }
        }

        return [null, 'default'];
    }

    /**
     * @return array{?int, string}
     */
    private function secondaryCategory(Library $library, BookImportResolutionDTO $resolution): array
    {
        $categoryId = $this->category($library, $resolution->payload['category_secondary']);

        return [
            $categoryId,
            $categoryId === null ? 'default' : ($resolution->sources['category_secondary'] ?? 'default'),
        ];
    }

    public function forgetRolledBackCache(Library $library): void
    {
        unset($this->categoryIds[$library->id]);
        $this->books->forgetResolvedAuthors($library->id);
        $this->cobissCategories->forgetLibrary($library->id);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload, int $rowNumber): array
    {
        $cells = [];

        foreach ($payload as $field => $value) {
            if (is_array($value)) {
                if ($field !== 'authors' || ! array_is_list($value)
                    || count(array_filter($value, is_string(...))) !== count($value)) {
                    throw new BookImportRowFailureException(BookImportFailureEnum::InvalidFieldValue, $field);
                }

                $value = implode('; ', $value);
            }

            $cells[] = Cell::fromValue($value);
        }

        $fields = array_keys($payload);
        $result = $this->rows->map($cells, $rowNumber, new ExcelImportHeaderMapDTO('input', $rowNumber, $fields, array_flip($fields)));

        if ($result?->failureCode !== null) {
            throw new BookImportRowFailureException($result->failureCode, $result->failureField);
        }

        return $result?->payload ?? [];
    }

    private function category(Library $library, ?string $path): ?int
    {
        if (! filled($path)) {
            return null;
        }

        if (isset($this->categoryIds[$library->id][$path])) {
            return $this->categoryIds[$library->id][$path];
        }

        $parent = null;

        foreach (explode('/', $path) as $name) {
            if ($this->categories->key($name) === '') {
                throw new BookImportRowFailureException(BookImportFailureEnum::InvalidFieldValue, 'category');
            }

            $parent = $this->categories->findOrCreate([
                'library_id' => $library->id, 'parent_id' => $parent, 'name' => trim($name),
            ])->id;
        }

        return $this->categoryIds[$library->id][$path] = $parent;
    }
}
