<?php

use App\Enums\UserRole;
use App\Models\Library;
use App\Models\User;
use App\Services\ActiveLibraryService;
use App\Services\LibraryMembershipService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

test('library admin can remove a membership from a library it manages', function () {
    $admin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $library = Library::factory()->create();
    $admin->libraries()->attach($library->id);
    $member = User::factory()->create();
    $member->libraries()->attach($library->id);

    $this->actingAs($admin)->deleteJson("/api/v1/users/{$member->id}/libraries", [
        'library_ids' => [$library->id],
    ], membershipHeaders())->assertNoContent();

    $this->assertDatabaseMissing('library_user', [
        'library_id' => $library->id,
        'user_id' => $member->id,
    ]);
});

test('library admin cannot remove a membership from a library it does not manage', function () {
    $admin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $managed = Library::factory()->create();
    $admin->libraries()->attach($managed->id);
    $foreign = Library::factory()->create();
    $member = User::factory()->create();
    $member->libraries()->attach([$managed->id, $foreign->id]);

    $this->actingAs($admin)->deleteJson("/api/v1/users/{$member->id}/libraries", [
        'library_ids' => [$foreign->id],
    ], membershipHeaders())->assertStatus(422);

    $this->assertDatabaseHas('library_user', [
        'library_id' => $foreign->id,
        'user_id' => $member->id,
        'deleted_at' => null,
    ]);
});

test('library admin cannot deactivate or activate memberships', function () {
    $admin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $library = Library::factory()->create();
    $admin->libraries()->attach($library->id);
    $member = User::factory()->create();
    $member->libraries()->attach($library->id);

    $this->actingAs($admin)->postJson("/api/v1/users/{$member->id}/libraries/deactivate", [
        'library_ids' => [$library->id],
    ], membershipHeaders())->assertForbidden();

    $this->actingAs($admin)->postJson("/api/v1/users/{$member->id}/libraries/activate", [
        'library_ids' => [$library->id],
    ], membershipHeaders())->assertForbidden();
});

test('force delete is blocked at db level when the account has related records', function () {
    Schema::create('user_activity_log', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->restrictOnDelete();
        $table->string('note');
        $table->timestamps();
    });

    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    DB::table('user_activity_log')->insert([
        'user_id' => $user->id,
        'note' => 'zabelezena relacija',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)->deleteJson("/api/v1/users/{$user->id}/force", [], membershipHeaders())
        ->assertStatus(422);

    expect(User::withTrashed()->find($user->id))->not->toBeNull();

    Schema::drop('user_activity_log');
});

test('bulk deactivate skips users that are already deactivated in the active library', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $activeOne = User::factory()->create();
    $activeTwo = User::factory()->create();
    $inactive = User::factory()->create();
    $activeOne->libraries()->attach($library->id);
    $activeTwo->libraries()->attach($library->id);
    $inactive->libraries()->attach($library->id);
    app(LibraryMembershipService::class)->deactivateMemberships($inactive, [$library->id]);

    app(ActiveLibraryService::class)->set($admin, $library->id);

    $this->actingAs($admin)->postJson('/api/v1/users/memberships/deactivate', [
        'user_ids' => [$activeOne->id, $activeTwo->id, $inactive->id],
    ], membershipHeaders())->assertNoContent();

    expect(pivotDeletedAt($library, $activeOne))->not->toBeNull()
        ->and(pivotDeletedAt($library, $activeTwo))->not->toBeNull()
        ->and(pivotDeletedAt($library, $inactive))->not->toBeNull();
});

test('bulk deactivate returns 422 when none of the selected users has an active membership', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $inactive = User::factory()->create();
    $inactive->libraries()->attach($library->id);
    app(LibraryMembershipService::class)->deactivateMemberships($inactive, [$library->id]);

    app(ActiveLibraryService::class)->set($admin, $library->id);

    $this->actingAs($admin)->postJson('/api/v1/users/memberships/deactivate', [
        'user_ids' => [$inactive->id],
    ], membershipHeaders())->assertStatus(422);
});

test('bulk activate skips users that are already active in the active library', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $inactiveOne = User::factory()->create();
    $inactiveTwo = User::factory()->create();
    $active = User::factory()->create();
    $inactiveOne->libraries()->attach($library->id);
    $inactiveTwo->libraries()->attach($library->id);
    $active->libraries()->attach($library->id);
    app(LibraryMembershipService::class)->deactivateMemberships($inactiveOne, [$library->id]);
    app(LibraryMembershipService::class)->deactivateMemberships($inactiveTwo, [$library->id]);

    app(ActiveLibraryService::class)->set($admin, $library->id);

    $this->actingAs($admin)->postJson('/api/v1/users/memberships/activate', [
        'user_ids' => [$inactiveOne->id, $inactiveTwo->id, $active->id],
    ], membershipHeaders())->assertNoContent();

    expect(pivotDeletedAt($library, $inactiveOne))->toBeNull()
        ->and(pivotDeletedAt($library, $inactiveTwo))->toBeNull()
        ->and(pivotDeletedAt($library, $active))->toBeNull();
});

test('bulk remove removes memberships of members and skips non members', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $member = User::factory()->create();
    $nonMember = User::factory()->create();
    $member->libraries()->attach($library->id);

    app(ActiveLibraryService::class)->set($admin, $library->id);

    $this->actingAs($admin)->deleteJson('/api/v1/users/memberships', [
        'user_ids' => [$member->id, $nonMember->id],
    ], membershipHeaders())->assertNoContent();

    $this->assertDatabaseMissing('library_user', [
        'library_id' => $library->id,
        'user_id' => $member->id,
    ]);

    expect(User::withTrashed()->find($nonMember->id))->not->toBeNull();
});

test('membership bulk actions require an active library', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->postJson('/api/v1/users/memberships/deactivate', [
        'user_ids' => [$user->id],
    ], membershipHeaders())->assertStatus(422);
});

test('library admin can bulk remove memberships from its library but not bulk deactivate', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $library = Library::factory()->create();
    $libraryAdmin->libraries()->attach($library->id);
    $member = User::factory()->create();
    $member->libraries()->attach($library->id);

    app(ActiveLibraryService::class)->set($libraryAdmin, $library->id);

    $this->actingAs($libraryAdmin)->deleteJson('/api/v1/users/memberships', [
        'user_ids' => [$member->id],
    ], membershipHeaders())->assertNoContent();

    $this->assertDatabaseMissing('library_user', [
        'library_id' => $library->id,
        'user_id' => $member->id,
    ]);
});

test('library admin cannot bulk deactivate or activate memberships', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $library = Library::factory()->create();
    $libraryAdmin->libraries()->attach($library->id);
    $member = User::factory()->create();
    $member->libraries()->attach($library->id);

    app(ActiveLibraryService::class)->set($libraryAdmin, $library->id);

    $this->actingAs($libraryAdmin)->postJson('/api/v1/users/memberships/deactivate', [
        'user_ids' => [$member->id],
    ], membershipHeaders())->assertForbidden();

    $this->actingAs($libraryAdmin)->postJson('/api/v1/users/memberships/activate', [
        'user_ids' => [$member->id],
    ], membershipHeaders())->assertForbidden();
});

test('bulk force delete rolls back the whole batch when one account is protected', function () {
    Schema::create('user_activity_log_bulk', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->restrictOnDelete();
        $table->string('note');
        $table->timestamps();
    });

    $admin = User::factory()->superAdmin()->create();
    $clean = User::factory()->create();
    $protected = User::factory()->create();

    DB::table('user_activity_log_bulk')->insert([
        'user_id' => $protected->id,
        'note' => 'zabelezena relacija',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)->deleteJson('/api/v1/users/bulk/force', [
        'user_ids' => [$clean->id, $protected->id],
    ], membershipHeaders())->assertStatus(422);

    expect(User::withTrashed()->find($clean->id))->not->toBeNull()
        ->and(User::withTrashed()->find($protected->id))->not->toBeNull();

    Schema::drop('user_activity_log_bulk');
});
