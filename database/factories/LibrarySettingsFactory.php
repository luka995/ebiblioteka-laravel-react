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
            'reservation_hours' => LibrarySettings::DEFAULT_RESERVATION_HOURS,
            'lend_days' => LibrarySettings::DEFAULT_LEND_DAYS,
            'overdue_price_per_day' => LibrarySettings::DEFAULT_OVERDUE_PRICE_PER_DAY,
        ];
    }
}
