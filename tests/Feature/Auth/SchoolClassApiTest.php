<?php

use App\Enums\LibraryRole;
use App\Enums\SchoolGrade;
use App\Models\ClassEnrollment;
use App\Models\ClassroomHistory;
use App\Models\Library;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\ActiveLibraryService;
use App\Services\GlobalSettings;
use App\Services\SchoolClassService;
use Illuminate\Validation\ValidationException;

function classApiHeaders(): array
{
    return ['Accept' => 'application/json'];
}

function classApiYear(int $year = 2026): SchoolYear
{
    return SchoolYear::factory()->forYear($year)->create();
}

function classApiClass(Library $library, SchoolYear $year, array $attributes = []): SchoolClass
{
    return SchoolClass::factory()
        ->for($library)
        ->for($year, 'schoolYear')
        ->create($attributes);
}

function classApiActivate(User $actor, Library $library): void
{
    app(ActiveLibraryService::class)->set($actor, $library->id);
}

test('staff can list classes scoped to the active library and active year', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $year = classApiYear(2026);
    $nextYear = classApiYear(2027);
    $librarian = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();

    classApiClass($library, $year, ['grade' => SchoolGrade::First, 'name' => '1-1']);
    classApiClass($library, $nextYear, ['grade' => SchoolGrade::First, 'name' => '1-1']);
    classApiClass($otherLibrary, $year, ['grade' => SchoolGrade::Second, 'name' => '2-1']);

    app(GlobalSettings::class)->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $year->id);
    classApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->getJson('/api/v1/classes', classApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', '1-1')
        ->assertJsonPath('data.0.school_year_id', $year->id)
        ->assertJsonPath('data.0.grade_label', 'I')
        ->assertJsonPath('permissions.viewAny', true)
        ->assertJsonPath('permissions.create', false);
});

test('staff can filter classes using the query string', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $year = classApiYear(2026);
    $otherYear = classApiYear(2027);
    $librarian = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();

    $matchingClass = classApiClass($library, $year, [
        'grade' => SchoolGrade::Second,
        'name' => '2-1',
        'teacher_name' => 'Mila Teacher',
        'is_active' => true,
    ]);
    classApiClass($library, $year, [
        'grade' => SchoolGrade::Second,
        'name' => '2-2',
        'teacher_name' => 'Mila Teacher',
        'is_active' => false,
    ]);
    classApiClass($library, $year, [
        'grade' => SchoolGrade::Third,
        'name' => '3-1',
        'teacher_name' => 'Mila Teacher',
        'is_active' => true,
    ]);
    classApiClass($library, $otherYear, [
        'grade' => SchoolGrade::Second,
        'name' => '2-3',
        'teacher_name' => 'Mila Teacher',
        'is_active' => true,
    ]);
    classApiClass($otherLibrary, $year, [
        'grade' => SchoolGrade::Second,
        'name' => '2-4',
        'teacher_name' => 'Mila Teacher',
        'is_active' => true,
    ]);

    classApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes?school_year_id={$year->id}&grade=2&search=Teacher&is_active=1", classApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingClass->id);

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes?school_year_id={$year->id}&grade=99", classApiHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('librarian can view classes and students but cannot create or update them', function () {
    $library = Library::factory()->create();
    $year = classApiYear();
    $librarian = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();
    $class = classApiClass($library, $year);

    classApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/{$class->id}", classApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.id', $class->id);

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/{$class->id}/students", classApiHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes', [
            'school_year_id' => $year->id,
            'grade' => 1,
            'name' => '1-1',
        ], classApiHeaders())
        ->assertForbidden();

    $this->actingAs($librarian)
        ->putJson("/api/v1/classes/{$class->id}", [
            'grade' => 2,
            'name' => '2-1',
        ], classApiHeaders())
        ->assertForbidden();
});

test('library admin can create update and delete a class', function () {
    $library = Library::factory()->create();
    $year = classApiYear();
    $admin = User::factory()->libraryRole($library, LibraryRole::LibraryAdmin)->create();

    app(GlobalSettings::class)->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $year->id);
    classApiActivate($admin, $library);

    $created = $this->actingAs($admin)
        ->postJson('/api/v1/classes', [
            'school_year_id' => $year->id,
            'grade' => 3,
            'name' => '3-1',
            'teacher_name' => 'Ana Anić',
        ], classApiHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', '3-1')
        ->assertJsonPath('data.grade_full_label', '3 (III)');

    $id = $created->json('data.id');

    $this->actingAs($admin)
        ->putJson("/api/v1/classes/{$id}", [
            'grade' => 4,
            'name' => '4-1',
            'teacher_name' => 'Marko Marković',
            'is_active' => false,
        ], classApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.name', '4-1')
        ->assertJsonPath('data.grade_label', 'IV')
        ->assertJsonPath('data.is_active', false);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/classes/{$id}", [], classApiHeaders())
        ->assertNoContent();

    expect(SchoolClass::query()->whereKey($id)->exists())->toBeFalse();
});

test('class creation validates grade school year and duplicate names', function () {
    $library = Library::factory()->create();
    $year = classApiYear();
    $admin = User::factory()->libraryRole($library, LibraryRole::LibraryAdmin)->create();
    classApiClass($library, $year, ['grade' => SchoolGrade::First, 'name' => '1-1']);

    app(GlobalSettings::class)->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $year->id);
    classApiActivate($admin, $library);

    $this->actingAs($admin)
        ->postJson('/api/v1/classes', [
            'school_year_id' => $year->id,
            'grade' => 9,
            'name' => '9-1',
        ], classApiHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('grade');

    $this->actingAs($admin)
        ->postJson('/api/v1/classes', [
            'school_year_id' => $year->id,
            'grade' => 1,
            'name' => '1-1',
        ], classApiHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');

    $this->actingAs($admin)
        ->postJson('/api/v1/classes', [
            'school_year_id' => 99999,
            'grade' => 1,
            'name' => '1-2',
        ], classApiHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('school_year_id');
});

test('class creation is rejected when there is no active school year', function () {
    $library = Library::factory()->create();
    $year = classApiYear();
    $admin = User::factory()->libraryRole($library, LibraryRole::LibraryAdmin)->create();
    classApiActivate($admin, $library);

    $this->actingAs($admin)
        ->postJson('/api/v1/classes', ['school_year_id' => $year->id, 'grade' => 1, 'name' => '1-1'], classApiHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('errors.school_year_id.0', __('validation.custom.active_school_year_required'));

    expect(SchoolClass::query()->where('library_id', $library->id)->exists())->toBeFalse();
});

test('class creation is rejected for an inactive school year', function () {
    $library = Library::factory()->create();
    $activeYear = classApiYear(2026);
    $otherYear = classApiYear(2027);
    $admin = User::factory()->libraryRole($library, LibraryRole::LibraryAdmin)->create();

    app(GlobalSettings::class)->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $activeYear->id);
    classApiActivate($admin, $library);

    $this->actingAs($admin)
        ->postJson('/api/v1/classes', ['school_year_id' => $otherYear->id, 'grade' => 1, 'name' => '1-1'], classApiHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('errors.school_year_id.0', __('validation.custom.class_active_school_year_required'));

    expect(SchoolClass::query()->where('library_id', $library->id)->exists())->toBeFalse();
});

test('class creation service rejects a year other than the active year', function () {
    $library = Library::factory()->create();
    $year = classApiYear(2026);
    $otherYear = classApiYear(2027);
    app(GlobalSettings::class)->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $year->id);

    expect(fn () => app(SchoolClassService::class)->create([
        'school_year_id' => $otherYear->id,
        'grade' => 1,
        'name' => '1-1',
    ], $library))->toThrow(ValidationException::class);

    expect(SchoolClass::query()->where('library_id', $library->id)->exists())->toBeFalse();
});

test('only a superadmin can change the school year of a class', function () {
    $library = Library::factory()->create();
    $year = classApiYear(2026);
    $otherYear = classApiYear(2027);
    $class = classApiClass($library, $year, ['name' => '1-1']);
    $admin = User::factory()->libraryRole($library, LibraryRole::LibraryAdmin)->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $payload = ['school_year_id' => $otherYear->id, 'grade' => 1, 'name' => '1-1'];

    classApiActivate($admin, $library);
    $this->actingAs($admin)
        ->putJson("/api/v1/classes/{$class->id}", $payload, classApiHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('errors.school_year_id.0', __('validation.custom.class_year_super_admin_only'));

    expect(fn () => app(SchoolClassService::class)->update($class, $payload, $admin))
        ->toThrow(ValidationException::class);

    classApiActivate($superAdmin, $library);
    $this->actingAs($superAdmin)
        ->putJson("/api/v1/classes/{$class->id}", $payload, classApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.school_year_id', $otherYear->id);
});

test('changing a class year checks for duplicate names in the destination year', function () {
    $library = Library::factory()->create();
    $year = classApiYear(2026);
    $otherYear = classApiYear(2027);
    $class = classApiClass($library, $year, ['name' => '1-1']);
    classApiClass($library, $otherYear, ['name' => '1-1']);
    $admin = User::factory()->superAdmin()->create();
    classApiActivate($admin, $library);

    $this->actingAs($admin)
        ->putJson("/api/v1/classes/{$class->id}", [
            'school_year_id' => $otherYear->id, 'grade' => 1, 'name' => '1-1',
        ], classApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect($class->refresh()->school_year_id)->toBe($year->id);
});

test('a superadmin cannot change the year of a class with enrollments or history', function () {
    $library = Library::factory()->create();
    $year = classApiYear(2026);
    $otherYear = classApiYear(2027);
    $class = classApiClass($library, $year, ['name' => '1-1']);
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->libraryRole($library, LibraryRole::User)->create();
    $service = app(SchoolClassService::class);
    $payload = ['school_year_id' => $otherYear->id, 'grade' => 1, 'name' => '1-1'];

    classApiActivate($admin, $library);
    $service->assignStudents($class, [$student->id], $admin);

    $this->actingAs($admin)
        ->putJson("/api/v1/classes/{$class->id}", $payload, classApiHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('errors.school_year_id.0', __('validation.custom.class_year_has_records'));

    $service->unassignStudents($class, [$student->id], $admin);

    $this->actingAs($admin)
        ->putJson("/api/v1/classes/{$class->id}", $payload, classApiHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('errors.school_year_id.0', __('validation.custom.class_year_has_records'));

    expect($class->refresh()->school_year_id)->toBe($year->id);
});

test('a class year cannot change when it appears only as a previous class in history', function () {
    $library = Library::factory()->create();
    $year = classApiYear(2026);
    $otherYear = classApiYear(2027);
    $class = classApiClass($library, $year, ['name' => '1-1']);
    $admin = User::factory()->superAdmin()->create();
    $student = User::factory()->libraryRole($library, LibraryRole::User)->create();
    ClassroomHistory::query()->create([
        'user_id' => $student->id,
        'library_id' => $library->id,
        'school_year_id' => $year->id,
        'class_id' => null,
        'previous_class_id' => $class->id,
        'previous_school_year_id' => $year->id,
        'changed_by_id' => $admin->id,
        'changed_at' => now(),
    ]);

    expect(fn () => app(SchoolClassService::class)->update($class, [
        'school_year_id' => $otherYear->id, 'grade' => 1, 'name' => '1-1',
    ], $admin))->toThrow(ValidationException::class);

    expect($class->refresh()->school_year_id)->toBe($year->id);
});

test('classes are scoped to the active library', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $year = classApiYear();
    $admin = User::factory()->libraryRole($library, LibraryRole::LibraryAdmin)->create();
    $foreignClass = classApiClass($otherLibrary, $year);

    classApiActivate($admin, $library);

    $this->actingAs($admin)
        ->getJson("/api/v1/classes/{$foreignClass->id}", classApiHeaders())
        ->assertForbidden();

    $this->actingAs($admin)
        ->putJson("/api/v1/classes/{$foreignClass->id}", [
            'grade' => 1,
            'name' => '1-2',
        ], classApiHeaders())
        ->assertForbidden();
});

test('a class with students or assignment history cannot be deleted', function () {
    $library = Library::factory()->create();
    $year = classApiYear();
    $admin = User::factory()->libraryRole($library, LibraryRole::LibraryAdmin)->create();
    $student = User::factory()->libraryRole($library, LibraryRole::User)->create();
    $class = classApiClass($library, $year);
    $otherClass = classApiClass($library, $year, ['name' => '1-2']);

    classApiActivate($admin, $library);

    app(SchoolClassService::class)->assignStudents($class, [$student->id], $admin);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/classes/{$class->id}", [], classApiHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('class');

    app(SchoolClassService::class)->unassignStudents($class, [$student->id], $admin);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/classes/{$class->id}", [], classApiHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('class');

    expect(ClassEnrollment::query()->where('class_id', $otherClass->id)->exists())->toBeFalse();
});

test('class options expose school years and grades', function () {
    $library = Library::factory()->create();
    $year = classApiYear(2026);
    $nextYear = classApiYear(2027);
    $librarian = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();

    app(GlobalSettings::class)->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $year->id);
    classApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->getJson('/api/v1/classes/options', classApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.active_school_year_id', $year->id)
        ->assertJsonPath('data.school_years.0.id', $nextYear->id)
        ->assertJsonCount(8, 'data.grades')
        ->assertJsonPath('data.grades.0.full_label', '1 (I)');
});
