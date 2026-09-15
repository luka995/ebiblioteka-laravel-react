<?php

namespace Database\Factories;

use App\Models\BookInventorySequence;
use App\Models\Library;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookInventorySequence>
 */
class BookInventorySequenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'last_number' => 0,
        ];
    }
}
