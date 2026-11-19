<?php

namespace Database\Seeders;

use App\Models\SchoolYear;
use App\Services\GlobalSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Idempotentno obezbedjuje tekuću i narednu skolsku godinu i aktivna
 * podesavanja. Postojece godine i aktivnu godinu ne prepisuje.
 */
class SchoolYearSeeder extends Seeder
{
    public function run(): void
    {
        $startYear = now()->month >= 9 ? now()->year : now()->year - 1;

        $active = $this->schoolYear($startYear);
        $this->schoolYear($startYear + 1);

        $settings = app(GlobalSettings::class);
        $activeId = (int) $settings->get(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID);

        if ($activeId <= 0 || ! SchoolYear::query()->whereKey($activeId)->exists()) {
            $settings->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $active->id);
        }

        if (! $settings->has(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION)) {
            $settings->set(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION, false);
        }
    }

    private function schoolYear(int $year): SchoolYear
    {
        return SchoolYear::query()->firstOrCreate(
            ['year' => $year],
            [
                'starts_on' => Carbon::create($year, 9, 1)->toDateString(),
                'ends_on' => Carbon::create($year + 1, 8, 31)->toDateString(),
            ],
        );
    }
}
