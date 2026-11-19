<?php

use App\Enums\SchoolGrade;
use App\Models\ClassEnrollment;
use App\Models\ClassroomHistory;
use App\Models\Library;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\GlobalSettings;
use Database\Seeders\SchoolYearSeeder;

test('a school year exposes its name and casts dates', function () {
    $year = SchoolYear::factory()->forYear(2026)->create();

    expect($year->name)->toBe('2026/2027')
        ->and($year->starts_on->toDateString())->toBe('2026-09-01')
        ->and($year->ends_on->toDateString())->toBe('2027-08-31');
});

test('a school class casts grade and exposes students and relations', function () {
    $library = Library::factory()->create();
    $year = SchoolYear::factory()->forYear(2026)->create();
    $class = SchoolClass::factory()->for($library)->for($year, 'schoolYear')->create([
        'grade' => SchoolGrade::Third,
        'name' => '3-1',
    ]);

    $student = User::factory()->create();
    $enrollment = ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'user_id' => $student->id,
        'library_id' => $library->id,
        'school_year_id' => $year->id,
    ]);

    expect($class->grade)->toBe(SchoolGrade::Third)
        ->and($class->grade->getFullLabel())->toBe('3 (III)')
        ->and($class->library->is($library))->toBeTrue()
        ->and($class->schoolYear->is($year))->toBeTrue()
        ->and($enrollment->schoolClass->is($class))->toBeTrue()
        ->and($enrollment->schoolYear->is($year))->toBeTrue()
        ->and($class->students()->pluck('users.id')->all())->toBe([$student->id])
        ->and($student->classes()->pluck('classes.id')->all())->toBe([$class->id]);
});

test('classroom history links the previous and the new class', function () {
    $library = Library::factory()->create();
    $year = SchoolYear::factory()->forYear(2026)->create();
    $previous = SchoolClass::factory()->for($library)->for($year, 'schoolYear')->create([
        'grade' => SchoolGrade::First,
        'name' => '1-1',
    ]);
    $next = SchoolClass::factory()->for($library)->for($year, 'schoolYear')->create([
        'grade' => SchoolGrade::First,
        'name' => '1-2',
    ]);
    $student = User::factory()->create();

    $history = ClassroomHistory::factory()->create([
        'user_id' => $student->id,
        'library_id' => $library->id,
        'school_year_id' => $year->id,
        'previous_class_id' => $previous->id,
        'class_id' => $next->id,
    ]);

    expect($history->previousClass->is($previous))->toBeTrue()
        ->and($history->schoolClass->is($next))->toBeTrue()
        ->and($history->user->is($student))->toBeTrue()
        ->and($history->changed_at)->not->toBeNull();
});

test('school year seeder creates the current and next year and activates the current one', function () {
    $this->seed(SchoolYearSeeder::class);

    $startYear = now()->month >= 9 ? now()->year : now()->year - 1;
    $active = SchoolYear::query()->where('year', $startYear)->first();

    expect($active)->not->toBeNull()
        ->and(SchoolYear::query()->where('year', $startYear + 1)->exists())->toBeTrue();

    $settings = app(GlobalSettings::class);

    expect((int) $settings->get(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID))->toBe($active->id)
        ->and($settings->get(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION))->toBeFalse();
});

test('a user gets their class by library and defaults to the active school year', function () {
    $library = Library::factory()->create();
    $activeYear = SchoolYear::factory()->forYear(2026)->create();
    $otherYear = SchoolYear::factory()->forYear(2025)->create();
    $student = User::factory()->create();
    $inactiveStudent = User::factory()->create();
    $activeClass = SchoolClass::factory()->for($library)->for($activeYear, 'schoolYear')->create();
    $otherClass = SchoolClass::factory()->for($library)->for($otherYear, 'schoolYear')->create();
    $inactiveClass = SchoolClass::factory()->for($library)->for($activeYear, 'schoolYear')->create([
        'is_active' => false,
    ]);

    $student->classes()->attach([
        $activeClass->id => ['library_id' => $library->id, 'school_year_id' => $activeYear->id],
        $otherClass->id => ['library_id' => $library->id, 'school_year_id' => $otherYear->id],
    ]);
    $inactiveStudent->classes()->attach($inactiveClass->id, [
        'library_id' => $library->id,
        'school_year_id' => $activeYear->id,
    ]);
    app(GlobalSettings::class)->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $activeYear->id);

    expect($student->getClassByLibrary($library)->is($activeClass))->toBeTrue()
        ->and($student->getClassByLibrary($library, $otherYear)->is($otherClass))->toBeTrue()
        ->and($inactiveStudent->getClassByLibrary($library))->toBeNull();
});
