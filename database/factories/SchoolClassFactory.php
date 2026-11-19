<?php

namespace Database\Factories;

use App\Models\Library;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'school_year_id' => SchoolYear::factory(),
            'grade' => fake()->numberBetween(1, 8),
            'name' => 'class-'.fake()->unique()->numberBetween(1, 999999),
            'teacher_name' => fake()->name(),
            'is_active' => true,
        ];
    }
}
