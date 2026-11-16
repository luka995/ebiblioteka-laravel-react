<?php

namespace Database\Factories;

use App\Models\Library;
use App\Models\LibrarySettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibrarySettings>
 */
class LibrarySettingsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            ...LibrarySettings::defaults(),
        ];
    }
}
