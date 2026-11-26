<?php

namespace App\Services\BookImports;

use App\Enums\BookImportFailureEnum;
use App\Exceptions\BookImportRowFailureException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookInventorySequence;
use App\Models\Library;
use App\Services\InventoryNumberService;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class BookImportCopyService
{
    public const FIELDS = [
        'seq_number', 'isbn', 'publisher', 'publish_place', 'publish_year', 'issue_number',
        'num_of_pages', 'dimension', 'part', 'udk', 'binding', 'origin', 'book_number',
        'place_on_shelf', 'price', 'notice',
    ];

    public function __construct(private readonly InventoryNumberService $numbers) {}

    public function validateInventory(Library $library, ?string $value): string
    {
        if ($value === null || trim($value) === '') {
            throw new BookImportRowFailureException(BookImportFailureEnum::MissingInventoryNumber);
        }

        try {
            $normalized = $this->numbers->normalizeOrderNumber($value);
        } catch (InvalidArgumentException) {
            throw new BookImportRowFailureException(BookImportFailureEnum::InvalidInventoryNumber);
        }

        if (strlen($normalized) > 12) {
            throw new BookImportRowFailureException(BookImportFailureEnum::InvalidInventoryNumber);
        }

        if ($this->numbers->orderNumberExists($library, $normalized)) {
            throw new BookImportRowFailureException(BookImportFailureEnum::DuplicateInventoryNumber);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Book $book, Library $library, BookInventorySequence $sequence, array $payload, bool $hasInventory): BookCopy
    {
        if (($payload['seq_number'] ?? null) !== null && BookCopy::withTrashed()
            ->where('library_id', $library->id)->where('seq_number', $payload['seq_number'])->exists()) {
            throw new BookImportRowFailureException(BookImportFailureEnum::DuplicateSeqNumber);
        }

        if ($hasInventory) {
            $orderNumber = $this->validateInventory($library, $payload['inventory_number'] ?? null);
        } else {
            if ($sequence->last_number >= 999999999999) {
                throw new BookImportRowFailureException(BookImportFailureEnum::InventoryCollision);
            }

            $orderNumber = $this->numbers->next($library);
        }

        $copy = BookCopy::create([
            ...Arr::only($payload, self::FIELDS),
            'library_id' => $library->id,
            'book_id' => $book->id,
            'order_number' => $orderNumber,
            'barcode' => $this->numbers->barcodeFor($orderNumber),
            'date_add' => today()->toDateString(),
            'price' => $payload['price'] ?? '0.00',
        ]);
        $this->numbers->recordManual($library, $orderNumber);

        return $copy;
    }
}
