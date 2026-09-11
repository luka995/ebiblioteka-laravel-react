<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Library;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'library_id' => Library::factory(),
            'parent_id' => null,
        ];
    }

    /**
     * Podkategorija date roditeljske kategorije (nasleđuje njenu biblioteku).
     */
    public function childOf(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'library_id' => $parent->library_id,
            'parent_id' => $parent->id,
        ]);
    }
}
