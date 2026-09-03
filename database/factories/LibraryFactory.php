<?php

namespace Database\Factories;

use App\Models\Library;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Library>
 */
class LibraryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' biblioteka',
            'address' => fake()->streetAddress(),
            'place_id' => Place::factory(),
            'work_time' => 'Pon - Pet, 08:00 - 19:00',
            'deleted' => false,
        ];
    }
}
