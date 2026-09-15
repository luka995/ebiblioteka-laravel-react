<?php

namespace App\Services;

use App\Exceptions\InventoryDiscrepancyException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kreiranje i izmena fizickih jedinica.
 *
 * Auto rezim: broj kopija se zadaje, inventarni brojevi se generisu iz
 * per-library sekvence, a barkod se izvodi iz broja. Pre generisanja proverava
 * se usklađenost (arhiva) i blokira se skok.
 *
 * Rucni rezim (`inv_number_auto=false`): jedna kopija po zahtevu, inventarni
 * broj se unosi rucno (slobodan, bez skoka).
 */
class BookCopyService
{
    private const METADATA = [
        'seq_number', 'isbn', 'publisher', 'publish_place', 'publish_year',
        'issue_number', 'num_of_pages', 'dimension', 'part', 'udk', 'binding',
        'origin', 'book_number', 'place_on_shelf', 'price', 'date_add', 'notice',
        'rec_error', 'rec_error_notice',
    ];

    public function __construct(
        private readonly InventoryNumberService $numbers,
        private readonly InventoryReconciliationService $reconciliation,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, BookCopy>
     */
    public function createForBook(Book $book, array $data): Collection
    {
        /** @var Library $library */
        $library = $book->library;
        $attributes = Arr::only($data, self::METADATA);

        if ($library->inv_number_auto) {
            $discrepancy = $this->reconciliation->inspect($library);

            if ($discrepancy !== null) {
                throw new InventoryDiscrepancyException($discrepancy);
            }

            $count = max(1, (int) ($data['copies'] ?? 1));

            return DB::transaction(function () use ($book, $library, $attributes, $count): Collection {
                $created = collect();

                for ($index = 0; $index < $count; $index++) {
                    $orderNumber = $this->numbers->next($library);
                    $created->push($this->persist($book, $attributes, $orderNumber));
                }

                return $created;
            });
        }

        $orderNumber = $this->reconciliation->validateManual($library, (string) $data['order_number']);

        return DB::transaction(function () use ($book, $library, $attributes, $orderNumber): Collection {
            $copy = $this->persist($book, $attributes, $orderNumber);
            $this->numbers->recordManual($library, $orderNumber);

            return collect([$copy]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BookCopy $copy, array $data): BookCopy
    {
        $copy->fill(Arr::only($data, self::METADATA));

        if (! $copy->trashed() && array_key_exists('order_number', $data)) {
            $this->applyOrderNumberChange($copy, (string) $data['order_number']);
        }

        $copy->save();

        return $copy;
    }

    private function applyOrderNumberChange(BookCopy $copy, string $orderNumber): void
    {
        if ($orderNumber === '' || (string) $copy->order_number === (string) (int) $orderNumber) {
            return;
        }

        // Promena inventarnog broja je dozvoljena samo u rucnom rezimu.
        if ($copy->library->inv_number_auto) {
            return;
        }

        $normalized = $this->reconciliation->validateManual($copy->library, $orderNumber, $copy);

        $copy->order_number = $normalized;
        $copy->barcode = $this->numbers->barcodeFor($normalized);
        $this->numbers->recordManual($copy->library, $normalized);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(Book $book, array $attributes, string $orderNumber): BookCopy
    {
        return BookCopy::create(array_merge($attributes, [
            'library_id' => $book->library_id,
            'book_id' => $book->id,
            'order_number' => $orderNumber,
            'barcode' => $this->numbers->barcodeFor($orderNumber),
        ]));
    }
}
