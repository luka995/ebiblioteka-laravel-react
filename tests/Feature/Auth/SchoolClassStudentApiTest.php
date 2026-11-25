<?php

use App\Enums\LibraryRole;
use App\Models\ClassEnrollment;
use App\Models\ClassroomHistory;
use App\Models\Library;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\ActiveLibraryService;

function classStudentHeaders(): array
{
    return ['Accept' => 'application/json'];
}

function classStudentYear(int $year = 2026): SchoolYear
{
    return SchoolYear::factory()->forYear($year)->create();
}

function classStudentClass(Library $library, SchoolYear $year, string $name): SchoolClass
{
    return SchoolClass::factory()
        ->for($library)
        ->for($year, 'schoolYear')
        ->create(['name' => $name]);
}

function classStudentActivate(User $actor, Library $library): void
{
    app(ActiveLibraryService::class)->set($actor, $library->id);
}

function classStudentUser(Library $library): User
{
    return User::factory()->libraryRole($library, LibraryRole::User)->create();
}

test('staff can assign existing library users to a class', function () {
    $library = Library::factory()->create();
    $year = classStudentYear();
    $class = classStudentClass($library, $year, '1-1');
    $librarian = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();
    $first = classStudentUser($library);
    $second = classStudentUser($library);

    classStudentActivate($librarian, $library);

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/{$class->id}/available-students", classStudentHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($librarian)
        ->postJson("/api/v1/classes/{$class->id}/students/assign", [
            'user_ids' => [$first->id, $second->id],
        ], classStudentHeaders())
        ->assertOk()
        ->assertJsonPath('assigned', 2)
        ->assertJsonPath('moved', 0)
        ->assertJsonPath('already_assigned', 0);

    expect(ClassEnrollment::query()->where('class_id', $class->id)->count())->toBe(2)
        ->and(ClassroomHistory::query()->where('class_id', $class->id)->count())->toBe(2)
        ->and(ClassroomHistory::query()->where('class_id', $class->id)->whereNull('previous_class_id')->count())->toBe(2);

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/{$class->id}/students?per_page=1", classStudentHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.assigned_by', $librarian->displayName());

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/{$class->id}/students?per_page=1&page=2", classStudentHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/{$class->id}/available-students", classStudentHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('assigning counts existing, new and moved students without duplicating history', function () {
    $library = Library::factory()->create();
    $year = classStudentYear();
    $class = classStudentClass($library, $year, '1-1');
    $previousClass = classStudentClass($library, $year, '1-2');
    $librarian = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();
    $existing = classStudentUser($library);
    $new = classStudentUser($library);
    $moved = classStudentUser($library);

    classStudentActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson("/api/v1/classes/{$class->id}/students/assign", [
            'user_ids' => [$existing->id],
        ], classStudentHeaders())
        ->assertOk();

    $this->actingAs($librarian)
        ->postJson("/api/v1/classes/{$previousClass->id}/students/assign", [
            'user_ids' => [$moved->id],
        ], classStudentHeaders())
        ->assertOk();

    $this->actingAs($librarian)
        ->postJson("/api/v1/classes/{$class->id}/students/assign", [
            'user_ids' => [$existing->id, $new->id, $moved->id],
        ], classStudentHeaders())
        ->assertOk()
        ->assertJsonPath('assigned', 1)
        ->assertJsonPath('moved', 1)
        ->assertJsonPath('already_assigned', 1);

    expect(ClassEnrollment::query()->where('class_id', $class->id)->count())->toBe(3)
        ->and(ClassroomHistory::query()->where('user_id', $existing->id)->count())->toBe(1)
        ->and(ClassroomHistory::query()->where('user_id', $new->id)->count())->toBe(1)
        ->and(ClassroomHistory::query()->where('user_id', $moved->id)->count())->toBe(2);
});

test('assigning a non-member or a staff user is rejected', function () {
    $library = Library::factory()->create();
    $year = classStudentYear();
    $class = classStudentClass($library, $year, '1-1');
    $librarian = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();
    $nonMember = User::factory()->create();
    $staffUser = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();

    classStudentActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson("/api/v1/classes/{$class->id}/students/assign", [
            'user_ids' => [$nonMember->id, $staffUser->id],
        ], classStudentHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_ids');

    expect(ClassEnrollment::query()->count())->toBe(0);
});

test('moving a student between classes updates the current row and history', function () {
    $library = Library::factory()->create();
    $year = classStudentYear();
    $firstClass = classStudentClass($library, $year, '1-1');
    $secondClass = classStudentClass($library, $year, '1-2');
    $librarian = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();
    $student = classStudentUser($library);

    classStudentActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson("/api/v1/classes/{$firstClass->id}/students/assign", [
            'user_ids' => [$student->id],
        ], classStudentHeaders())
        ->assertOk();

    $this->actingAs($librarian)
        ->postJson("/api/v1/classes/{$secondClass->id}/students/assign", [
            'user_ids' => [$student->id],
        ], classStudentHeaders())
        ->assertOk()
        ->assertJsonPath('assigned', 0)
        ->assertJsonPath('moved', 1)
        ->assertJsonPath('already_assigned', 0);

    expect(ClassEnrollment::query()->where('user_id', $student->id)->count())->toBe(1)
        ->and(ClassEnrollment::query()->where('user_id', $student->id)->value('class_id'))->toBe($secondClass->id);

    $history = ClassroomHistory::query()
        ->where('user_id', $student->id)
        ->orderBy('id')
        ->get();

    expect($history)->toHaveCount(2)
        ->and($history[0]->previous_class_id)->toBeNull()
        ->and($history[0]->previous_school_year_id)->toBeNull()
        ->and($history[0]->class_id)->toBe($firstClass->id)
        ->and($history[1]->previous_class_id)->toBe($firstClass->id)
        ->and($history[1]->previous_school_year_id)->toBe($year->id)
        ->and($history[1]->class_id)->toBe($secondClass->id);
});

test('unassigning a student removes the current row and records history', function () {
    $library = Library::factory()->create();
    $year = classStudentYear();
    $class = classStudentClass($library, $year, '1-1');
    $librarian = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();
    $student = classStudentUser($library);

    classStudentActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson("/api/v1/classes/{$class->id}/students/assign", [
            'user_ids' => [$student->id],
        ], classStudentHeaders())
        ->assertOk();

    $this->actingAs($librarian)
        ->postJson("/api/v1/classes/{$class->id}/students/unassign", [
            'user_ids' => [$student->id],
        ], classStudentHeaders())
        ->assertOk()
        ->assertJsonPath('removed', 1);

    expect(ClassEnrollment::query()->where('user_id', $student->id)->exists())->toBeFalse();

    $history = ClassroomHistory::query()
        ->where('user_id', $student->id)
        ->orderByDesc('id')
        ->first();

    expect($history)->not->toBeNull()
        ->and($history->previous_class_id)->toBe($class->id)
        ->and($history->previous_school_year_id)->toBe($year->id)
        ->and($history->class_id)->toBeNull();
});

test('available students show the current class and exclude class members', function () {
    $library = Library::factory()->create();
    $year = classStudentYear();
    $firstClass = classStudentClass($library, $year, '1-1');
    $secondClass = classStudentClass($library, $year, '1-2');
    $librarian = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();
    $student = classStudentUser($library);

    classStudentActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson("/api/v1/classes/{$firstClass->id}/students/assign", [
            'user_ids' => [$student->id],
        ], classStudentHeaders())
        ->assertOk();

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/{$secondClass->id}/available-students?search={$student->email}", classStudentHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.current_class.id', $firstClass->id);

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/{$firstClass->id}/available-students?search={$student->email}", classStudentHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('an inactive class cannot receive students', function () {
    $library = Library::factory()->create();
    $year = classStudentYear();
    $class = classStudentClass($library, $year, '1-1');
    $class->update(['is_active' => false]);
    $librarian = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();
    $student = classStudentUser($library);

    classStudentActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson("/api/v1/classes/{$class->id}/students/assign", [
            'user_ids' => [$student->id],
        ], classStudentHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('class');
});

test('class students are scoped to the active library', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $year = classStudentYear();
    $foreignClass = classStudentClass($otherLibrary, $year, '1-1');
    $librarian = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();

    classStudentActivate($librarian, $library);

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/{$foreignClass->id}/students", classStudentHeaders())
        ->assertForbidden();

    $this->actingAs($librarian)
        ->postJson("/api/v1/classes/{$foreignClass->id}/students/assign", [
            'user_ids' => [],
        ], classStudentHeaders())
        ->assertForbidden();
});
