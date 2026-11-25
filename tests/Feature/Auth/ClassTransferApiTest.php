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
use App\Services\ClassTransferService;
use App\Services\GlobalSettings;
use App\Services\SchoolClassService;
use Illuminate\Validation\ValidationException;

function transferApiHeaders(): array
{
    return ['Accept' => 'application/json'];
}

function transferApiYear(int $year): SchoolYear
{
    return SchoolYear::factory()->forYear($year)->create();
}

function transferApiClass(Library $library, SchoolYear $year, int $grade, string $name): SchoolClass
{
    return SchoolClass::factory()
        ->for($library)
        ->for($year, 'schoolYear')
        ->create([
            'grade' => SchoolGrade::from($grade),
            'name' => $name,
        ]);
}

function transferApiStudent(Library $library, SchoolClass $class): User
{
    $user = User::factory()->libraryRole($library, LibraryRole::User)->create();

    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'user_id' => $user->id,
    ]);

    return $user;
}

function transferApiStaff(Library $library): User
{
    return User::factory()->libraryRole($library, LibraryRole::Librarian)->create();
}

function transferApiActivate(User $actor, Library $library): void
{
    app(ActiveLibraryService::class)->set($actor, $library->id);
}

function transferApiActiveYear(SchoolYear $year): void
{
    app(GlobalSettings::class)->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $year->id);
}

test('preview proposes target classes for the next school year', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    $targetYear = transferApiYear(2027);

    $first = transferApiClass($library, $sourceYear, 1, '1-1');
    $second = transferApiClass($library, $sourceYear, 1, '1-2');
    $eighth = transferApiClass($library, $sourceYear, 8, '8-1');

    transferApiStudent($library, $first);
    transferApiStudent($library, $first);
    transferApiStudent($library, $second);
    transferApiStudent($library, $eighth);

    $firstTarget = transferApiClass($library, $targetYear, 2, '2-1');
    $secondTarget = transferApiClass($library, $targetYear, 2, '2-2');

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/transfer/preview?source_school_year_id={$sourceYear->id}", transferApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.source_year.id', $sourceYear->id)
        ->assertJsonPath('data.target_year.id', $targetYear->id)
        ->assertJsonPath('data.target_year_exists', true)
        ->assertJsonPath('data.can_transfer', true)
        ->assertJsonPath('data.classes.0.id', $first->id)
        ->assertJsonPath('data.classes.0.suggested_target_class_id', $firstTarget->id)
        ->assertJsonPath('data.classes.0.status', 'ready')
        ->assertJsonPath('data.classes.1.id', $second->id)
        ->assertJsonPath('data.classes.1.suggested_target_class_id', $secondTarget->id)
        ->assertJsonPath('data.classes.2.id', $eighth->id)
        ->assertJsonPath('data.classes.2.status', 'graduating')
        ->assertJsonPath('data.summary.students_count', 4)
        ->assertJsonPath('data.summary.eligible_students_count', 3)
        ->assertJsonPath('data.summary.graduating_students_count', 1);
});

test('preview reports a missing next school year without failing', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    transferApiStudent($library, transferApiClass($library, $sourceYear, 1, '1-1'));

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/transfer/preview?source_school_year_id={$sourceYear->id}", transferApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.target_year', null)
        ->assertJsonPath('data.target_year_exists', false)
        ->assertJsonPath('data.can_transfer', false)
        ->assertJsonPath('data.classes.0.status', 'needs_target')
        ->assertJsonPath('data.classes.0.can_create_target', false);
});

test('preview marks a class without eligible students as ineligible', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    transferApiYear(2027);
    $sourceClass = transferApiClass($library, $sourceYear, 1, '1-1');
    $ineligible = User::factory()->create();

    ClassEnrollment::factory()->create([
        'class_id' => $sourceClass->id,
        'user_id' => $ineligible->id,
    ]);

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/transfer/preview?source_school_year_id={$sourceYear->id}", transferApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.classes.0.status', 'ineligible')
        ->assertJsonPath('data.classes.0.eligible_students_count', 0)
        ->assertJsonPath('data.can_transfer', false);
});

test('preview rejects source years earlier than the year before the active year', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2024);
    transferApiActiveYear(transferApiYear(2026));

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/transfer/preview?source_school_year_id={$sourceYear->id}", transferApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('source_school_year_id');
});

test('preview is scoped to the active library', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $sourceYear = transferApiYear(2026);

    transferApiClass($otherLibrary, $sourceYear, 1, '1-1');
    transferApiClass($library, $sourceYear, 1, '1-2');

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/transfer/preview?source_school_year_id={$sourceYear->id}", transferApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data.classes')
        ->assertJsonPath('data.classes.0.name', '1-2');
});

test('transfer moves students into the next grade and records cross-year history', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    $targetYear = transferApiYear(2027);
    $sourceClass = transferApiClass($library, $sourceYear, 1, '1-1');
    $targetClass = transferApiClass($library, $targetYear, 2, '2-1');
    $first = transferApiStudent($library, $sourceClass);
    $second = transferApiStudent($library, $sourceClass);

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', [
            'source_school_year_id' => $sourceYear->id,
            'target_school_year_id' => $targetYear->id,
            'mappings' => [
                ['source_class_id' => $sourceClass->id, 'target_class_id' => $targetClass->id],
            ],
        ], transferApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.totals.transferred', 2)
        ->assertJsonPath('data.totals.already_enrolled', 0)
        ->assertJsonPath('data.totals.created_target_classes', 0);

    expect(ClassEnrollment::query()->where('class_id', $targetClass->id)->count())->toBe(2)
        ->and(ClassEnrollment::query()->where('class_id', $sourceClass->id)->count())->toBe(2);

    $history = ClassroomHistory::query()
        ->where('user_id', $first->id)
        ->firstOrFail();

    expect($history->previous_class_id)->toBe($sourceClass->id)
        ->and($history->previous_school_year_id)->toBe($sourceYear->id)
        ->and($history->class_id)->toBe($targetClass->id)
        ->and($history->school_year_id)->toBe($targetYear->id)
        ->and($history->changed_by_id)->toBe($librarian->id)
        ->and(ClassroomHistory::query()->where('user_id', $second->id)->count())->toBe(1);
});

test('transfer only processes selected classes and leaves unselected classes untouched', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    $targetYear = transferApiYear(2027);
    $firstSource = transferApiClass($library, $sourceYear, 1, '1-1');
    $secondSource = transferApiClass($library, $sourceYear, 1, '1-2');
    $firstTarget = transferApiClass($library, $targetYear, 2, '2-1');
    $secondTarget = transferApiClass($library, $targetYear, 2, '2-2');
    $firstStudent = transferApiStudent($library, $firstSource);
    $secondStudent = transferApiStudent($library, $secondSource);

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', [
            'source_school_year_id' => $sourceYear->id,
            'target_school_year_id' => $targetYear->id,
            'mappings' => [
                ['source_class_id' => $firstSource->id, 'target_class_id' => $firstTarget->id],
            ],
        ], transferApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.totals.classes', 1)
        ->assertJsonPath('data.totals.students', 1)
        ->assertJsonPath('data.totals.transferred', 1)
        ->assertJsonCount(1, 'data.classes');

    expect(ClassEnrollment::query()->where('class_id', $firstTarget->id)->value('user_id'))->toBe($firstStudent->id)
        ->and(ClassEnrollment::query()->where('class_id', $secondTarget->id)->exists())->toBeFalse()
        ->and(ClassEnrollment::query()->where('class_id', $secondSource->id)->value('user_id'))->toBe($secondStudent->id);
});

test('transfer is idempotent for students already enrolled in the target class', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    $targetYear = transferApiYear(2027);
    $sourceClass = transferApiClass($library, $sourceYear, 1, '1-1');
    $targetClass = transferApiClass($library, $targetYear, 2, '2-1');
    transferApiStudent($library, $sourceClass);
    transferApiStudent($library, $sourceClass);

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $payload = [
        'source_school_year_id' => $sourceYear->id,
        'target_school_year_id' => $targetYear->id,
        'mappings' => [
            ['source_class_id' => $sourceClass->id, 'target_class_id' => $targetClass->id],
        ],
    ];

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', $payload, transferApiHeaders())
        ->assertOk();

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', $payload, transferApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.totals.transferred', 0)
        ->assertJsonPath('data.totals.already_enrolled', 2);

    expect(ClassEnrollment::query()->where('class_id', $targetClass->id)->count())->toBe(2)
        ->and(ClassroomHistory::query()->where('school_year_id', $targetYear->id)->count())->toBe(2);
});

test('transfer creates missing target classes when requested', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    $targetYear = transferApiYear(2027);
    $first = transferApiClass($library, $sourceYear, 1, '1-1');
    $second = transferApiClass($library, $sourceYear, 1, '1-2');
    $eighth = transferApiClass($library, $sourceYear, 8, '8-1');
    transferApiStudent($library, $first);
    transferApiStudent($library, $first);
    transferApiStudent($library, $second);
    transferApiStudent($library, $eighth);

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', [
            'source_school_year_id' => $sourceYear->id,
            'target_school_year_id' => $targetYear->id,
            'create_missing_target_classes' => true,
            'mappings' => [
                ['source_class_id' => $first->id, 'target_class_id' => null],
                ['source_class_id' => $second->id, 'target_class_id' => null],
            ],
        ], transferApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.totals.transferred', 3)
        ->assertJsonPath('data.totals.graduating', 1)
        ->assertJsonPath('data.totals.created_target_classes', 2);

    $created = SchoolClass::query()
        ->where('library_id', $library->id)
        ->where('school_year_id', $targetYear->id)
        ->orderBy('name')
        ->get();

    expect($created)->toHaveCount(2)
        ->and($created[0]->name)->toBe('2-1')
        ->and($created[0]->grade)->toBe(SchoolGrade::Second)
        ->and($created[1]->name)->toBe('2-2')
        ->and($created[1]->grade)->toBe(SchoolGrade::Second)
        ->and(SchoolClass::query()->where('grade', 9)->exists())->toBeFalse();
});

test('transfer requires the immediately following school year', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    $tooFarYear = transferApiYear(2028);
    $sourceClass = transferApiClass($library, $sourceYear, 1, '1-1');
    $targetClass = transferApiClass($library, $tooFarYear, 2, '2-1');
    transferApiStudent($library, $sourceClass);

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', [
            'source_school_year_id' => $sourceYear->id,
            'target_school_year_id' => $tooFarYear->id,
            'mappings' => [
                ['source_class_id' => $sourceClass->id, 'target_class_id' => $targetClass->id],
            ],
        ], transferApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('target_school_year_id');
});

test('transfer request rejects source years earlier than the year before the active year', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2024);
    $targetYear = transferApiYear(2025);
    $activeYear = transferApiYear(2026);
    $sourceClass = transferApiClass($library, $sourceYear, 1, '1-1');
    $targetClass = transferApiClass($library, $targetYear, 2, '2-1');
    transferApiStudent($library, $sourceClass);
    transferApiActiveYear($activeYear);

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', [
            'source_school_year_id' => $sourceYear->id,
            'target_school_year_id' => $targetYear->id,
            'mappings' => [
                ['source_class_id' => $sourceClass->id, 'target_class_id' => $targetClass->id],
            ],
        ], transferApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source_school_year_id', 'target_school_year_id']);
});

test('transfer allows the year immediately before the active year as its source', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2025);
    $targetYear = transferApiYear(2026);
    $sourceClass = transferApiClass($library, $sourceYear, 1, '1-1');
    $targetClass = transferApiClass($library, $targetYear, 2, '2-1');
    transferApiStudent($library, $sourceClass);
    transferApiActiveYear($targetYear);

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', [
            'source_school_year_id' => $sourceYear->id,
            'target_school_year_id' => $targetYear->id,
            'mappings' => [
                ['source_class_id' => $sourceClass->id, 'target_class_id' => $targetClass->id],
            ],
        ], transferApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.totals.transferred', 1);
});

test('transfer service rejects target years earlier than the active year', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2024);
    $targetYear = transferApiYear(2025);
    $activeYear = transferApiYear(2026);
    $actor = transferApiStaff($library);
    transferApiActiveYear($activeYear);

    expect(fn () => app(ClassTransferService::class)->transfer(
        $library,
        $sourceYear,
        $targetYear,
        [],
        false,
        $actor,
    ))->toThrow(ValidationException::class);
});

test('transfer rejects a target class from the wrong grade or an inactive class', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    $targetYear = transferApiYear(2027);
    $sourceClass = transferApiClass($library, $sourceYear, 1, '1-1');
    $wrongGrade = transferApiClass($library, $targetYear, 3, '3-1');
    $inactive = transferApiClass($library, $targetYear, 2, '2-1');
    $inactive->update(['is_active' => false]);
    transferApiStudent($library, $sourceClass);

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $payload = [
        'source_school_year_id' => $sourceYear->id,
        'target_school_year_id' => $targetYear->id,
        'mappings' => [
            ['source_class_id' => $sourceClass->id, 'target_class_id' => $wrongGrade->id],
        ],
    ];

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', $payload, transferApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mappings');

    $payload['mappings'][0]['target_class_id'] = $inactive->id;

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', $payload, transferApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mappings');
});

test('transfer rejects a target class from another library', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    $targetYear = transferApiYear(2027);
    $sourceClass = transferApiClass($library, $sourceYear, 1, '1-1');
    $foreignTarget = transferApiClass($otherLibrary, $targetYear, 2, '2-1');
    transferApiStudent($library, $sourceClass);

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', [
            'source_school_year_id' => $sourceYear->id,
            'target_school_year_id' => $targetYear->id,
            'mappings' => [
                ['source_class_id' => $sourceClass->id, 'target_class_id' => $foreignTarget->id],
            ],
        ], transferApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mappings');
});

test('transfer requires a mapping when target classes exist', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    $targetYear = transferApiYear(2027);
    $sourceClass = transferApiClass($library, $sourceYear, 1, '1-1');
    transferApiClass($library, $targetYear, 2, '2-1');
    transferApiStudent($library, $sourceClass);

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', [
            'source_school_year_id' => $sourceYear->id,
            'target_school_year_id' => $targetYear->id,
            'mappings' => [
                ['source_class_id' => $sourceClass->id, 'target_class_id' => null],
            ],
        ], transferApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mappings');
});

test('transfer rejects a mapping for eighth grade', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    $targetYear = transferApiYear(2027);
    $sourceClass = transferApiClass($library, $sourceYear, 8, '8-1');
    $targetClass = transferApiClass($library, $targetYear, 2, '2-1');
    transferApiStudent($library, $sourceClass);

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', [
            'source_school_year_id' => $sourceYear->id,
            'target_school_year_id' => $targetYear->id,
            'mappings' => [
                ['source_class_id' => $sourceClass->id, 'target_class_id' => $targetClass->id],
            ],
        ], transferApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mappings');
});

test('transfer skips ineligible students and students already enrolled in another target class', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    $targetYear = transferApiYear(2027);
    $sourceClass = transferApiClass($library, $sourceYear, 1, '1-1');
    $targetClass = transferApiClass($library, $targetYear, 2, '2-1');
    $otherTargetClass = transferApiClass($library, $targetYear, 2, '2-2');
    $active = transferApiStudent($library, $sourceClass);
    $ineligible = User::factory()->create();

    ClassEnrollment::factory()->create([
        'class_id' => $sourceClass->id,
        'user_id' => $ineligible->id,
    ]);
    ClassEnrollment::factory()->create([
        'class_id' => $otherTargetClass->id,
        'user_id' => $active->id,
    ]);

    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', [
            'source_school_year_id' => $sourceYear->id,
            'target_school_year_id' => $targetYear->id,
            'mappings' => [
                ['source_class_id' => $sourceClass->id, 'target_class_id' => $targetClass->id],
            ],
        ], transferApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.totals.transferred', 0)
        ->assertJsonPath('data.totals.already_enrolled', 0)
        ->assertJsonPath('data.totals.skipped_conflict', 1)
        ->assertJsonPath('data.totals.skipped_ineligible', 1);
});

test('transfer works when the target school year is not active', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    $targetYear = transferApiYear(2027);
    $sourceClass = transferApiClass($library, $sourceYear, 1, '1-1');
    $targetClass = transferApiClass($library, $targetYear, 2, '2-1');
    transferApiStudent($library, $sourceClass);

    transferApiActiveYear($sourceYear);
    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->postJson('/api/v1/classes/transfer', [
            'source_school_year_id' => $sourceYear->id,
            'target_school_year_id' => $targetYear->id,
            'mappings' => [
                ['source_class_id' => $sourceClass->id, 'target_class_id' => $targetClass->id],
            ],
        ], transferApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.totals.transferred', 1);
});

test('transfer service revalidates the source and target pair before writing', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    $tooFarYear = transferApiYear(2028);
    $sourceClass = transferApiClass($library, $sourceYear, 1, '1-1');
    $targetClass = transferApiClass($library, $tooFarYear, 2, '2-1');
    $student = transferApiStudent($library, $sourceClass);
    $actor = transferApiStaff($library);

    expect(fn () => app(SchoolClassService::class)->transferStudentsToClass(
        $sourceClass,
        $targetClass,
        [$student->id],
        $actor,
    ))->toThrow(ValidationException::class);
});

test('class transfer permissions and active library are required', function () {
    $library = Library::factory()->create();
    $sourceYear = transferApiYear(2026);
    transferApiClass($library, $sourceYear, 1, '1-1');

    $secondLibrary = Library::factory()->create();
    $librarian = User::factory()
        ->libraryRole($library, LibraryRole::Librarian)
        ->libraryRole($secondLibrary, LibraryRole::Librarian)
        ->create();

    $this->actingAs($librarian)
        ->getJson("/api/v1/classes/transfer/preview?source_school_year_id={$sourceYear->id}", transferApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('library_id');

    $regular = User::factory()->libraryRole($library, LibraryRole::User)->create();
    transferApiActivate($regular, $library);

    $this->actingAs($regular)
        ->getJson("/api/v1/classes/transfer/preview?source_school_year_id={$sourceYear->id}", transferApiHeaders())
        ->assertForbidden();
});

test('class options expose transfer metadata and default source year', function () {
    $library = Library::factory()->create();
    $previousYear = transferApiYear(2025);
    $activeYear = transferApiYear(2026);
    $nextYear = transferApiYear(2027);
    transferApiClass($library, $previousYear, 1, '1-1');
    transferApiClass($library, $activeYear, 2, '2-1');
    transferApiClass($library, $activeYear, 2, '2-2');

    transferApiActiveYear($activeYear);
    $librarian = transferApiStaff($library);
    transferApiActivate($librarian, $library);

    $this->actingAs($librarian)
        ->getJson('/api/v1/classes/options', transferApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.active_school_year_id', $activeYear->id)
        ->assertJsonPath('data.default_transfer_source_school_year_id', $previousYear->id)
        ->assertJsonPath('data.school_years.0.id', $nextYear->id)
        ->assertJsonPath('data.school_years.1.classes_count', 2)
        ->assertJsonPath('data.school_years.1.previous_school_year_id', $previousYear->id)
        ->assertJsonPath('data.school_years.2.next_school_year_id', $activeYear->id);
});
