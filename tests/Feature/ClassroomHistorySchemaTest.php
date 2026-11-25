<?php

use App\Enums\SchoolGrade;
use App\Models\ClassroomHistory;
use App\Models\Library;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Database\QueryException;

function historyClass(Library $library, SchoolYear $year, string $name, SchoolGrade $grade): SchoolClass
{
    return SchoolClass::factory()
        ->for($library)
        ->for($year, 'schoolYear')
        ->create([
            'name' => $name,
            'grade' => $grade,
        ]);
}

test('classroom history can link classes from different school years', function () {
    $library = Library::factory()->create();
    $sourceYear = SchoolYear::factory()->forYear(2025)->create();
    $targetYear = SchoolYear::factory()->forYear(2026)->create();
    $sourceClass = historyClass($library, $sourceYear, '1-1', SchoolGrade::First);
    $targetClass = historyClass($library, $targetYear, '2-1', SchoolGrade::Second);
    $student = User::factory()->create();

    $history = ClassroomHistory::factory()->create([
        'user_id' => $student->id,
        'library_id' => $library->id,
        'school_year_id' => $targetYear->id,
        'class_id' => $targetClass->id,
        'previous_school_year_id' => $sourceYear->id,
        'previous_class_id' => $sourceClass->id,
    ]);

    expect($history->previousClass->is($sourceClass))->toBeTrue()
        ->and($history->previousSchoolYear->is($sourceYear))->toBeTrue()
        ->and($history->schoolClass->is($targetClass))->toBeTrue()
        ->and($history->schoolYear->is($targetYear))->toBeTrue();
});

test('classroom history requires a previous school year when a previous class is set', function () {
    $library = Library::factory()->create();
    $sourceYear = SchoolYear::factory()->forYear(2025)->create();
    $targetYear = SchoolYear::factory()->forYear(2026)->create();
    $sourceClass = historyClass($library, $sourceYear, '1-1', SchoolGrade::First);
    $targetClass = historyClass($library, $targetYear, '2-1', SchoolGrade::Second);
    $student = User::factory()->create();

    expect(fn () => ClassroomHistory::factory()->create([
        'user_id' => $student->id,
        'library_id' => $library->id,
        'school_year_id' => $targetYear->id,
        'class_id' => $targetClass->id,
        'previous_school_year_id' => null,
        'previous_class_id' => $sourceClass->id,
    ]))->toThrow(QueryException::class);
});

test('classroom history requires a previous class when a previous school year is set', function () {
    $library = Library::factory()->create();
    $sourceYear = SchoolYear::factory()->forYear(2025)->create();
    $targetYear = SchoolYear::factory()->forYear(2026)->create();
    $targetClass = historyClass($library, $targetYear, '2-1', SchoolGrade::Second);
    $student = User::factory()->create();

    expect(fn () => ClassroomHistory::factory()->create([
        'user_id' => $student->id,
        'library_id' => $library->id,
        'school_year_id' => $targetYear->id,
        'class_id' => $targetClass->id,
        'previous_school_year_id' => $sourceYear->id,
        'previous_class_id' => null,
    ]))->toThrow(QueryException::class);
});

test('the previous class must belong to the same library', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $sourceYear = SchoolYear::factory()->forYear(2025)->create();
    $targetYear = SchoolYear::factory()->forYear(2026)->create();
    $foreignSourceClass = historyClass($otherLibrary, $sourceYear, '1-1', SchoolGrade::First);
    $targetClass = historyClass($library, $targetYear, '2-1', SchoolGrade::Second);
    $student = User::factory()->create();

    expect(fn () => ClassroomHistory::factory()->create([
        'user_id' => $student->id,
        'library_id' => $library->id,
        'school_year_id' => $targetYear->id,
        'class_id' => $targetClass->id,
        'previous_school_year_id' => $sourceYear->id,
        'previous_class_id' => $foreignSourceClass->id,
    ]))->toThrow(QueryException::class);
});
