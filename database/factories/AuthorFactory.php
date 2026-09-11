<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Library;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Author>
 */
class AuthorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->name(),
            'library_id' => Library::factory(),
        ];
    }
}
