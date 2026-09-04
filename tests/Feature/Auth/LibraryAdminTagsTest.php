<?php

use App\Enums\UserRole;
use App\Models\Library;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use App\Models\User;

function libraryAdminHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function makeAdminLibrary(): Library
{
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();

    return Library::factory()->for($place)->create();
}

function makeLibraryAdmin(Library $library): User
{
    $admin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $admin->libraries()->attach($library->id);

    return $admin;
}

test('library admin can list only their own users', function () {
    $library = makeAdminLibrary();
    $other = makeAdminLibrary();
    $admin = makeLibraryAdmin($library);

    $own = User::factory()->create();
    $own->libraries()->attach($library->id);
    $foreign = User::factory()->create();
    $foreign->libraries()->attach($other->id);

    $this->actingAs($admin)->getJson('/api/v1/users', libraryAdminHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $own->id])
        ->assertJsonMissing(['id' => $foreign->id]);
});

test('library admin can create a user within their library', function () {
    $library = makeAdminLibrary();
    $admin = makeLibraryAdmin($library);

    $this->actingAs($admin)->postJson('/api/v1/users', [
        'first_name' => 'Petar',
        'last_name' => 'Petrović',
        'username' => 'petar.petrovic',
        'email' => 'petar@example.com',
        'password' => 'secret123',
        'role' => 'user',
        'libraries' => [$library->id],
    ], libraryAdminHeaders())
        ->assertCreated()
        ->assertJsonPath('data.libraries.0.id', $library->id);
});

test('library admin cannot create a user in a library they do not manage', function () {
    $library = makeAdminLibrary();
    $other = makeAdminLibrary();
    $admin = makeLibraryAdmin($library);

    $this->actingAs($admin)->postJson('/api/v1/users', [
        'first_name' => 'Petar',
        'last_name' => 'Petrović',
        'username' => 'petar.petrovic2',
        'email' => 'petar2@example.com',
        'password' => 'secret123',
        'role' => 'user',
        'libraries' => [$other->id],
    ], libraryAdminHeaders())
        ->assertUnprocessable();
});

test('library admin cannot delete or force delete a user', function () {
    $library = makeAdminLibrary();
    $admin = makeLibraryAdmin($library);
    $user = User::factory()->create();
    $user->libraries()->attach($library->id);

    $this->actingAs($admin)->deleteJson("/api/v1/users/{$user->id}", [], libraryAdminHeaders())
        ->assertForbidden();

    $this->actingAs($admin)->deleteJson("/api/v1/users/{$user->id}/force", [], libraryAdminHeaders())
        ->assertForbidden();
});

test('library admin cannot manage memberships', function () {
    $library = makeAdminLibrary();
    $admin = makeLibraryAdmin($library);
    $user = User::factory()->create();
    $user->libraries()->attach($library->id);

    $this->actingAs($admin)->postJson("/api/v1/users/{$user->id}/libraries/deactivate", [
        'library_ids' => [$library->id],
    ], libraryAdminHeaders())
        ->assertForbidden();
});

test('library admin can create update and delete their own tag', function () {
    $library = makeAdminLibrary();
    $admin = makeLibraryAdmin($library);

    $this->actingAs($admin)->postJson('/api/v1/tags', [
        'name' => 'Redovni',
        'library_id' => $library->id,
    ], libraryAdminHeaders())->assertCreated();

    $tag = Tag::where('name', 'Redovni')->firstOrFail();

    $this->actingAs($admin)->putJson("/api/v1/tags/{$tag->id}", [
        'name' => 'Vip',
        'library_id' => $library->id,
    ], libraryAdminHeaders())->assertOk();

    $this->actingAs($admin)->deleteJson("/api/v1/tags/{$tag->id}", [], libraryAdminHeaders())
        ->assertNoContent();

    expect(Tag::find($tag->id))->toBeNull();
});

test('library admin cannot create a tag for another library', function () {
    $library = makeAdminLibrary();
    $other = makeAdminLibrary();
    $admin = makeLibraryAdmin($library);

    $this->actingAs($admin)->postJson('/api/v1/tags', [
        'name' => 'Tudji',
        'library_id' => $other->id,
    ], libraryAdminHeaders())->assertUnprocessable();
});

test('library admin cannot update or delete a foreign tag', function () {
    $library = makeAdminLibrary();
    $admin = makeLibraryAdmin($library);
    $foreign = Tag::factory()->create();

    $this->actingAs($admin)->putJson("/api/v1/tags/{$foreign->id}", [
        'name' => 'Zabranjeno',
        'library_id' => $foreign->library_id,
    ], libraryAdminHeaders())->assertForbidden();

    $this->actingAs($admin)->deleteJson("/api/v1/tags/{$foreign->id}", [], libraryAdminHeaders())
        ->assertForbidden();
});

test('library admin can bulk assign and remove tags for their users', function () {
    $library = makeAdminLibrary();
    $admin = makeLibraryAdmin($library);
    $tag = Tag::factory()->for($library)->create();

    $first = User::factory()->create();
    $first->libraries()->attach($library->id);
    $second = User::factory()->create();
    $second->libraries()->attach($library->id);

    $this->actingAs($admin)->postJson('/api/v1/users/tags/assign', [
        'user_ids' => [$first->id, $second->id],
        'library_id' => $library->id,
        'tag_ids' => [$tag->id],
    ], libraryAdminHeaders())->assertNoContent();

    expect($first->tags()->pluck('tags.id')->all())->toContain($tag->id)
        ->and($second->tags()->pluck('tags.id')->all())->toContain($tag->id);

    $this->actingAs($admin)->postJson('/api/v1/users/tags/remove', [
        'user_ids' => [$first->id],
        'library_id' => $library->id,
        'tag_ids' => [$tag->id],
    ], libraryAdminHeaders())->assertNoContent();

    expect($first->tags()->pluck('tags.id')->all())->not->toContain($tag->id)
        ->and($second->tags()->pluck('tags.id')->all())->toContain($tag->id);
});

test('bulk tag assign rejects a tag from another library', function () {
    $library = makeAdminLibrary();
    $admin = makeLibraryAdmin($library);
    $foreign = Tag::factory()->create();
    $user = User::factory()->create();
    $user->libraries()->attach($library->id);

    $this->actingAs($admin)->postJson('/api/v1/users/tags/assign', [
        'user_ids' => [$user->id],
        'library_id' => $library->id,
        'tag_ids' => [$foreign->id],
    ], libraryAdminHeaders())->assertUnprocessable();
});

test('bulk tag assign rejects a user who is not a library member', function () {
    $library = makeAdminLibrary();
    $admin = makeLibraryAdmin($library);
    $tag = Tag::factory()->for($library)->create();
    $nonMember = User::factory()->create();

    $this->actingAs($admin)->postJson('/api/v1/users/tags/assign', [
        'user_ids' => [$nonMember->id],
        'library_id' => $library->id,
        'tag_ids' => [$tag->id],
    ], libraryAdminHeaders())->assertUnprocessable();
});

test('librarian and regular user cannot access users or tags', function () {
    $librarian = User::factory()->role(UserRole::Librarian)->create();
    $regular = User::factory()->create();

    $this->actingAs($librarian)->getJson('/api/v1/users', libraryAdminHeaders())->assertForbidden();
    $this->actingAs($librarian)->getJson('/api/v1/tags', libraryAdminHeaders())->assertForbidden();
    $this->actingAs($regular)->getJson('/api/v1/users', libraryAdminHeaders())->assertForbidden();
    $this->actingAs($regular)->getJson('/api/v1/tags', libraryAdminHeaders())->assertForbidden();
});
