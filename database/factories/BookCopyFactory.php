<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Support\BarCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookCopy>
 */
class BookCopyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $orderNumber = (string) fake()->unique()->numberBetween(1, 9_000_000);

        return [
            'library_id' => Library::factory(),
            'book_id' => Book::factory(),
            'order_number' => $orderNumber,
            'seq_number' => null,
            'barcode' => BarCode::generateFromBaseNumber($orderNumber),
            'isbn' => null,
            'publisher' => null,
            'publish_place' => null,
            'publish_year' => null,
            'issue_number' => null,
            'num_of_pages' => null,
            'dimension' => null,
            'part' => null,
            'udk' => null,
            'binding' => null,
            'origin' => null,
            'book_number' => null,
            'place_on_shelf' => null,
            'price' => 0,
            'date_add' => now()->toDateString(),
            'notice' => null,
            'borrowed' => false,
            'reserved' => false,
            'rec_error' => false,
            'rec_error_notice' => null,
        ];
    }
}
