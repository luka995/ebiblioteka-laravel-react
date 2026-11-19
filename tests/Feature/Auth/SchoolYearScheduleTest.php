<?php

use App\Models\SchoolYear;
use App\Services\GlobalSettings;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

test('the activation command activates a due school year when automatic activation is enabled', function () {
    Carbon::setTestNow(Carbon::create(2027, 9, 1, 0, 0, 0, config('app.timezone')));

    $active = SchoolYear::factory()->create([
        'year' => 2026,
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-08-31',
    ]);

    SchoolYear::factory()->create([
        'year' => 2027,
        'starts_on' => '2027-09-01',
        'ends_on' => '2028-08-31',
    ]);

    $settings = app(GlobalSettings::class);
    $settings->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $active->id);
    $settings->set(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION, true);
    $settings->flush();

    $this->artisan('school-years:activate')->assertSuccessful();

    $settings->flush();

    $next = SchoolYear::query()->where('year', 2027)->first();

    expect((int) $settings->get(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID))->toBe($next->id)
        ->and(SchoolYear::query()->where('year', 2028)->exists())->toBeFalse();
});

test('the activation command does nothing when automatic activation is disabled', function () {
    Carbon::setTestNow(Carbon::create(2027, 9, 1, 0, 0, 0, config('app.timezone')));

    $active = SchoolYear::factory()->create([
        'year' => 2026,
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-08-31',
    ]);

    SchoolYear::factory()->create([
        'year' => 2027,
        'starts_on' => '2027-09-01',
        'ends_on' => '2028-08-31',
    ]);

    $settings = app(GlobalSettings::class);
    $settings->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $active->id);
    $settings->set(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION, false);
    $settings->flush();

    $this->artisan('school-years:activate')->assertSuccessful();

    $settings->flush();

    expect((int) $settings->get(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID))->toBe($active->id)
        ->and(SchoolYear::query()->where('year', 2028)->exists())->toBeFalse();
});

test('the activation command creates and activates the school year that starts today', function () {
    Carbon::setTestNow(Carbon::create(2028, 9, 1, 0, 0, 0, config('app.timezone')));

    $settings = app(GlobalSettings::class);
    $settings->set(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION, true);
    $settings->flush();

    $this->artisan('school-years:activate')->assertSuccessful();

    $settings->flush();
    $year = SchoolYear::query()->where('starts_on', '2028-09-01')->first();

    expect($year)->not->toBeNull()
        ->and($year->ends_on->toDateString())->toBe('2029-08-31')
        ->and((int) $settings->get(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID))->toBe($year->id)
        ->and(SchoolYear::query()->where('year', 2029)->exists())->toBeFalse();
});
