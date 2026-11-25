<?php

use App\Enums\LibraryRole;
use App\Models\Library;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\GlobalSettings;

function schoolYearPayload(int $year, ?string $startsOn = null, ?string $endsOn = null): array
{
    return [
        'year' => $year,
        'starts_on' => $startsOn ?? "{$year}-09-01",
        'ends_on' => $endsOn ?? ($year + 1).'-08-31',
    ];
}

function schoolYearHeaders(): array
{
    return ['Accept' => 'application/json'];
}

test('superadmin can list school years with metadata', function () {
    $admin = User::factory()->superAdmin()->create();
    $active = SchoolYear::factory()->forYear(2026)->create();
    SchoolYear::factory()->forYear(2027)->create();

    $settings = app(GlobalSettings::class);
    $settings->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $active->id);
    $settings->set(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION, true);

    $this->actingAs($admin)->getJson('/api/v1/system/school-years', schoolYearHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.year', 2027)
        ->assertJsonPath('data.0.name', '2027/2028')
        ->assertJsonPath('data.1.is_active', true)
        ->assertJsonPath('meta.active_school_year_id', $active->id)
        ->assertJsonPath('meta.automatic_activation', true);
});

test('school year list is paginated', function () {
    $admin = User::factory()->superAdmin()->create();
    SchoolYear::factory()->forYear(2026)->create();
    SchoolYear::factory()->forYear(2027)->create();

    $this->actingAs($admin)
        ->getJson('/api/v1/system/school-years?per_page=1', schoolYearHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2);
});

test('superadmin can create update and delete a school year', function () {
    $admin = User::factory()->superAdmin()->create();

    $created = $this->actingAs($admin)
        ->postJson('/api/v1/system/school-years', schoolYearPayload(2030), schoolYearHeaders())
        ->assertCreated()
        ->assertJsonPath('data.year', 2030)
        ->assertJsonPath('data.name', '2030/2031');

    $id = $created->json('data.id');

    $this->actingAs($admin)
        ->putJson("/api/v1/system/school-years/{$id}", schoolYearPayload(2031), schoolYearHeaders())
        ->assertOk()
        ->assertJsonPath('data.year', 2031)
        ->assertJsonPath('data.name', '2031/2032');

    $this->actingAs($admin)
        ->deleteJson("/api/v1/system/school-years/{$id}", [], schoolYearHeaders())
        ->assertNoContent();

    expect(SchoolYear::query()->whereKey($id)->exists())->toBeFalse();
});

test('school year validation rejects mismatched dates duplicates and overlaps', function () {
    $admin = User::factory()->superAdmin()->create();
    SchoolYear::factory()->forYear(2026)->create();

    $this->actingAs($admin)
        ->postJson('/api/v1/system/school-years', schoolYearPayload(2026), schoolYearHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('year');

    $this->actingAs($admin)
        ->postJson('/api/v1/system/school-years', schoolYearPayload(2030, '2031-09-01', '2032-08-31'), schoolYearHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('starts_on');

    $this->actingAs($admin)
        ->postJson('/api/v1/system/school-years', schoolYearPayload(2030, null, '2032-08-31'), schoolYearHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('ends_on');

    $this->actingAs($admin)
        ->postJson('/api/v1/system/school-years', schoolYearPayload(2027, '2027-06-01', '2028-05-31'), schoolYearHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('starts_on');
});

test('only superadmin can manage school years', function () {
    $library = Library::factory()->create();
    $libraryAdmin = User::factory()->libraryRole($library, LibraryRole::LibraryAdmin)->create();
    $librarian = User::factory()->libraryRole($library, LibraryRole::Librarian)->create();
    $member = User::factory()->libraryRole($library, LibraryRole::User)->create();

    foreach ([$libraryAdmin, $librarian, $member] as $user) {
        $this->actingAs($user)
            ->getJson('/api/v1/system/school-years', schoolYearHeaders())
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/api/v1/system/school-years', schoolYearPayload(2030), schoolYearHeaders())
            ->assertForbidden();
    }
});

test('activating a school year stores the active setting', function () {
    $admin = User::factory()->superAdmin()->create();
    $year = SchoolYear::factory()->forYear(2026)->create();

    $this->actingAs($admin)
        ->postJson("/api/v1/system/school-years/{$year->id}/activate", [], schoolYearHeaders())
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    $settings = app(GlobalSettings::class);
    $settings->flush();

    expect((int) $settings->get(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID))->toBe($year->id);
});

test('manual activation is blocked while automatic activation is enabled', function () {
    $admin = User::factory()->superAdmin()->create();
    $year = SchoolYear::factory()->forYear(2026)->create();

    app(GlobalSettings::class)->set(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION, true);

    $this->actingAs($admin)
        ->postJson("/api/v1/system/school-years/{$year->id}/activate", [], schoolYearHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('school_year');
});

test('automatic activation can be toggled by superadmin', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->putJson('/api/v1/system/school-years/automatic-activation', [
            'automatic_activation' => true,
        ], schoolYearHeaders())
        ->assertOk()
        ->assertJsonPath('automatic_activation', true);

    $settings = app(GlobalSettings::class);
    $settings->flush();

    expect($settings->get(GlobalSettings::SCHOOL_YEAR_AUTOMATIC_ACTIVATION))->toBeTrue();
});

test('active or used school years cannot be deleted', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $active = SchoolYear::factory()->forYear(2026)->create();
    $used = SchoolYear::factory()->forYear(2027)->create();

    app(GlobalSettings::class)->set(GlobalSettings::ACTIVE_SCHOOL_YEAR_ID, $active->id);

    SchoolClass::factory()
        ->for($library)
        ->for($used, 'schoolYear')
        ->create();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/system/school-years/{$active->id}", [], schoolYearHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('school_year');

    $this->actingAs($admin)
        ->deleteJson("/api/v1/system/school-years/{$used->id}", [], schoolYearHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('school_year');
});

test('a school year with classes cannot change its year label', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $year = SchoolYear::factory()->forYear(2026)->create();

    SchoolClass::factory()
        ->for($library)
        ->for($year, 'schoolYear')
        ->create();

    $this->actingAs($admin)
        ->putJson("/api/v1/system/school-years/{$year->id}", schoolYearPayload(2027), schoolYearHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors('year');

    $this->actingAs($admin)
        ->putJson("/api/v1/system/school-years/{$year->id}", schoolYearPayload(2026, '2026-09-02'), schoolYearHeaders())
        ->assertOk()
        ->assertJsonPath('data.starts_on', '2026-09-02');
});
