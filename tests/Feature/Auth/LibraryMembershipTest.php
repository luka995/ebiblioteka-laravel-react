<?php

use App\Enums\UserRole;
use App\Models\Library;
use App\Models\User;
use App\Services\ActiveLibraryService;
use App\Services\LibraryMembershipService;
use Illuminate\Support\Facades\DB;

function membershipHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function pivotDeletedAt(Library $library, User $user): ?string
{
    return DB::table('library_user')
        ->where('library_id', $library->id)
        ->where('user_id', $user->id)
        ->value('deleted_at');
}

test('library deactivation cascades to deactivate memberships but not accounts', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $member = User::factory()->create();
    $member->libraries()->attach($library->id);

    $this->actingAs($admin)->deleteJson("/api/v1/libraries/{$library->id}", [], membershipHeaders())
        ->assertNoContent();

    expect($library->fresh()->deleted)->toBeTrue()
        ->and(pivotDeletedAt($library, $member))->not->toBeNull()
        ->and(User::find($member->id))->not->toBeNull();
});

test('library restore reactivates library and its memberships', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $member = User::factory()->create();
    $member->libraries()->attach($library->id);

    app(LibraryMembershipService::class)->deactivateLibrary($library);

    $this->actingAs($admin)->postJson("/api/v1/libraries/{$library->id}/restore", [], membershipHeaders())
        ->assertNoContent();

    expect($library->fresh()->deleted)->toBeFalse()
        ->and(pivotDeletedAt($library, $member))->toBeNull();
});

test('restore is not available for an active library', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();

    $this->actingAs($admin)->postJson("/api/v1/libraries/{$library->id}/restore", [], membershipHeaders())
        ->assertNotFound();
});

test('deactivate memberships endpoint soft deletes selected pivots', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $a = Library::factory()->create();
    $b = Library::factory()->create();
    $user->libraries()->attach([$a->id, $b->id]);

    $this->actingAs($admin)->postJson("/api/v1/users/{$user->id}/libraries/deactivate", [
        'library_ids' => [$a->id],
    ], membershipHeaders())->assertNoContent();

    expect(pivotDeletedAt($a, $user))->not->toBeNull()
        ->and(pivotDeletedAt($b, $user))->toBeNull();
});

test('activate memberships endpoint restores deactivated pivots', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $library = Library::factory()->create();
    $user->libraries()->attach($library->id);
    app(LibraryMembershipService::class)->deactivateMemberships($user, [$library->id]);

    $this->actingAs($admin)->postJson("/api/v1/users/{$user->id}/libraries/activate", [
        'library_ids' => [$library->id],
    ], membershipHeaders())->assertNoContent();

    expect(pivotDeletedAt($library, $user))->toBeNull();
});

test('remove memberships endpoint force deletes pivot rows', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $library = Library::factory()->create();
    $user->libraries()->attach($library->id);

    $this->actingAs($admin)->deleteJson("/api/v1/users/{$user->id}/libraries", [
        'library_ids' => [$library->id],
    ], membershipHeaders())->assertNoContent();

    $this->assertDatabaseMissing('library_user', [
        'library_id' => $library->id,
        'user_id' => $user->id,
    ]);
});

test('force delete endpoint removes the account entirely', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $library = Library::factory()->create();
    $user->libraries()->attach($library->id);

    $this->actingAs($admin)->deleteJson("/api/v1/users/{$user->id}/force", [], membershipHeaders())
        ->assertNoContent();

    expect(User::withTrashed()->find($user->id))->toBeNull();

    $this->assertDatabaseMissing('library_user', [
        'library_id' => $library->id,
        'user_id' => $user->id,
    ]);
});

test('force delete is blocked for own account', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->deleteJson("/api/v1/users/{$admin->id}/force", [], membershipHeaders())
        ->assertStatus(422);

    expect(User::withTrashed()->find($admin->id))->not->toBeNull();
});

test('membership actions require an array of library ids', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->postJson("/api/v1/users/{$user->id}/libraries/deactivate", [
        'library_ids' => 'not-an-array',
    ], membershipHeaders())->assertUnprocessable();
});

test('syncMemberships hard removes removed libraries and leaves untouched trashed', function () {
    $user = User::factory()->create();
    $keep = Library::factory()->create();
    $remove = Library::factory()->create();
    $deactivated = Library::factory()->create();
    $user->libraries()->attach([$keep->id, $remove->id, $deactivated->id]);

    app(LibraryMembershipService::class)->deactivateMemberships($user, [$deactivated->id]);

    app(LibraryMembershipService::class)->syncMemberships($user, [$keep->id]);

    $this->assertDatabaseMissing('library_user', [
        'library_id' => $remove->id,
        'user_id' => $user->id,
    ]);

    expect(pivotDeletedAt($keep, $user))->toBeNull()
        ->and(pivotDeletedAt($deactivated, $user))->not->toBeNull();
});

test('syncMemberships restores a previously deactivated membership when re-added', function () {
    $user = User::factory()->create();
    $library = Library::factory()->create();
    $user->libraries()->attach($library->id);
    app(LibraryMembershipService::class)->deactivateMemberships($user, [$library->id]);

    app(LibraryMembershipService::class)->syncMemberships($user, [$library->id]);

    expect(pivotDeletedAt($library, $user))->toBeNull();
});

test('user resource exposes deactivated libraries', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $active = Library::factory()->create();
    $inactive = Library::factory()->create();
    $user->libraries()->attach([$active->id, $inactive->id]);
    app(LibraryMembershipService::class)->deactivateMemberships($user, [$inactive->id]);

    $this->actingAs($admin)->getJson("/api/v1/users/{$user->id}", membershipHeaders())
        ->assertOk()
        ->assertJsonPath('data.libraries.0.id', $active->id)
        ->assertJsonPath('data.deactivated_libraries.0.id', $inactive->id);
});

test('selectable libraries exclude deactivated memberships', function () {
    $user = User::factory()->role(UserRole::LibraryAdmin)->create();
    $active = Library::factory()->create();
    $inactive = Library::factory()->create();
    $user->libraries()->attach([$active->id, $inactive->id]);
    app(LibraryMembershipService::class)->deactivateMemberships($user, [$inactive->id]);

    $ids = app(ActiveLibraryService::class)->selectable($user)->pluck('id')->all();

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($inactive->id);
});
