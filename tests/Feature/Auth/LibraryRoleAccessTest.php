<?php

use App\Enums\LibraryRole;
use App\Models\Book;
use App\Models\Library;
use App\Models\User;
use App\Services\ActiveLibraryService;

function roleAccessHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ];
}

function userWithRoles(array $roles): User
{
    $user = User::factory()->create();

    foreach ($roles as [$library, $role]) {
        $user->libraries()->attach($library->id, ['role' => $role->value]);
    }

    return $user;
}

test('a user can have different roles in different libraries', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $user = userWithRoles([
        [$libraryA, LibraryRole::Librarian],
        [$libraryB, LibraryRole::User],
    ]);

    expect($user->roleIn($libraryA))->toBe(LibraryRole::Librarian)
        ->and($user->roleIn($libraryB))->toBe(LibraryRole::User)
        ->and($user->isStaffIn($libraryA))->toBeTrue()
        ->and($user->isStaffIn($libraryB))->toBeFalse()
        ->and($user->isStaffAnywhere())->toBeTrue()
        ->and($user->isSuperAdmin())->toBeFalse();
});

test('staff access follows the active library context', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $user = userWithRoles([
        [$libraryA, LibraryRole::Librarian],
        [$libraryB, LibraryRole::User],
    ]);
    Book::factory()->for($libraryA)->create();

    app(ActiveLibraryService::class)->set($user, $libraryA->id);
    $this->actingAs($user)->getJson('/api/v1/books', roleAccessHeaders())->assertOk();

    app(ActiveLibraryService::class)->set($user, $libraryB->id);
    $this->actingAs($user)->getJson('/api/v1/books', roleAccessHeaders())->assertForbidden();
});

test('library admin rights apply only to the library where the role is assigned', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $user = userWithRoles([
        [$libraryA, LibraryRole::LibraryAdmin],
        [$libraryB, LibraryRole::User],
    ]);

    $this->actingAs($user)
        ->putJson("/api/v1/libraries/{$libraryA->id}/settings", ['reservation_hours' => 24], roleAccessHeaders())
        ->assertOk();

    $this->actingAs($user)
        ->putJson("/api/v1/libraries/{$libraryB->id}/settings", ['reservation_hours' => 24], roleAccessHeaders())
        ->assertForbidden();
});

test('assignable roles depend on the role in the active library', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $user = userWithRoles([
        [$libraryA, LibraryRole::LibraryAdmin],
        [$libraryB, LibraryRole::User],
    ]);

    app(ActiveLibraryService::class)->set($user, $libraryA->id);
    $this->actingAs($user)->getJson('/api/v1/roles/assignable', roleAccessHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'roles');

    app(ActiveLibraryService::class)->set($user, $libraryB->id);
    $this->actingAs($user)->getJson('/api/v1/roles/assignable', roleAccessHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'roles');
});

test('a library admin must select an active library before listing users', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $admin = userWithRoles([
        [$libraryA, LibraryRole::LibraryAdmin],
        [$libraryB, LibraryRole::User],
    ]);

    $this->actingAs($admin)
        ->getJson('/api/v1/users', roleAccessHeaders())
        ->assertUnprocessable();

    $memberA = User::factory()->create();
    $memberA->libraries()->attach($libraryA->id);
    $memberB = User::factory()->create();
    $memberB->libraries()->attach($libraryB->id);

    app(ActiveLibraryService::class)->set($admin, $libraryA->id);

    $this->actingAs($admin)
        ->getJson('/api/v1/users', roleAccessHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $memberA->id])
        ->assertJsonMissing(['id' => $memberB->id]);
});

test('a library admin cannot change tags in a library where they are only a member', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $admin = userWithRoles([
        [$libraryA, LibraryRole::LibraryAdmin],
        [$libraryB, LibraryRole::User],
    ]);
    app(ActiveLibraryService::class)->set($admin, $libraryA->id);

    $target = User::factory()->create();
    $target->libraries()->attach($libraryA->id);
    $target->libraries()->attach($libraryB->id);
    $tag = App\Models\Tag::factory()->for($libraryB)->create();

    $this->actingAs($admin)->putJson("/api/v1/users/{$target->id}/tags", [
        'library_id' => $libraryB->id,
        'tag_ids' => [$tag->id],
    ], roleAccessHeaders())->assertStatus(422);
});

test('a library admin sees only their own libraries on a target user', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $admin = userWithRoles([
        [$libraryA, LibraryRole::LibraryAdmin],
    ]);
    app(ActiveLibraryService::class)->set($admin, $libraryA->id);

    $target = User::factory()->create();
    $target->libraries()->attach($libraryA->id, ['role' => LibraryRole::User->value]);
    $target->libraries()->attach($libraryB->id, ['role' => LibraryRole::Librarian->value]);

    $this->actingAs($admin)
        ->getJson("/api/v1/users/{$target->id}", roleAccessHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data.libraries')
        ->assertJsonPath('data.libraries.0.id', $libraryA->id)
        ->assertJsonPath('data.libraries.0.role', 'user');
});
