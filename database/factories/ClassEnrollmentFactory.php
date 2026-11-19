<?php

namespace Database\Factories;

use App\Models\ClassEnrollment;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassEnrollment>
 */
class ClassEnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'user_id' => User::factory(),
            // Composite FK zahteva da library_id i school_year_id odgovaraju
            // odeljenju, pa se izvode iz vec kreiranog class_id.
            'library_id' => fn (array $attributes): int => SchoolClass::query()
                ->findOrFail($attributes['class_id'])
                ->library_id,
            'school_year_id' => fn (array $attributes): int => SchoolClass::query()
                ->findOrFail($attributes['class_id'])
                ->school_year_id,
            'assigned_by_id' => null,
        ];
    }
}
