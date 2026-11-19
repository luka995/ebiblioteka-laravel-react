<?php

namespace Database\Factories;

use App\Models\ClassroomHistory;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassroomHistory>
 */
class ClassroomHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'previous_class_id' => null,
            'user_id' => User::factory(),
            'library_id' => fn (array $attributes): int => SchoolClass::query()
                ->findOrFail($attributes['class_id'])
                ->library_id,
            'school_year_id' => fn (array $attributes): int => SchoolClass::query()
                ->findOrFail($attributes['class_id'])
                ->school_year_id,
            'changed_by_id' => null,
            'changed_at' => now(),
        ];
    }
}
