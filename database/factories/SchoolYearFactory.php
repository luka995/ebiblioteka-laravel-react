<?php

namespace Database\Factories;

use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolYear>
 */
class SchoolYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2000, 2100);

        return [
            'year' => $year,
            'starts_on' => sprintf('%d-09-01', $year),
            'ends_on' => sprintf('%d-08-31', $year + 1),
            'created_by_id' => null,
        ];
    }

    public function forYear(int $year): static
    {
        return $this->state(fn (): array => [
            'year' => $year,
            'starts_on' => sprintf('%d-09-01', $year),
            'ends_on' => sprintf('%d-08-31', $year + 1),
        ]);
    }
}
