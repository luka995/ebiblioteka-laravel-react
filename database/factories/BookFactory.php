<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Library;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(3),
            'library_id' => Library::factory(),
            'category_primary_id' => null,
            'category_secondary_id' => null,
            'description' => fake()->paragraph(),
            'image' => null,
            'cover_url' => null,
        ];
    }
}
