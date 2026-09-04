<?php

use App\Enums\UserRole;
use App\Models\Library;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use App\Models\User;

function tagAssignHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function makeTagAssignLibrary(): Library
{
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();

    return Library::factory()->for($place)->create();
}

test('superadmin can assign tags to a user for a specific library', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeTagAssignLibrary();
    $tag = Tag::factory()->for($library)->create();

    $user = User::factory()->create();
    $user->libraries()->attach($library->id);

    $this->actingAs($admin)->putJson("/api/v1/users/{$user->id}/tags", [
        'library_id' => $library->id,
        'tag_ids' => [$tag->id],
    ], tagAssignHeaders())
        ->assertNoContent();

    expect($user->fresh()->tags->pluck('id')->all())->toContain($tag->id);
});

test('sync keeps tags of other libraries untouched', function () {
    $admin = User::factory()->superAdmin()->create();
    $libraryA = makeTagAssignLibrary();
    $libraryB = makeTagAssignLibrary();
    $tagA = Tag::factory()->for($libraryA)->create();
    $tagB1 = Tag::factory()->for($libraryB)->create();
    $tagB2 = Tag::factory()->for($libraryB)->create();

    $user = User::factory()->create();
    $user->libraries()->attach([$libraryA->id, $libraryB->id]);
    $user->tags()->attach([$tagA->id, $tagB1->id]);

    $this->actingAs($admin)->putJson("/api/v1/users/{$user->id}/tags", [
        'library_id' => $libraryB->id,
        'tag_ids' => [$tagB2->id],
    ], tagAssignHeaders())
        ->assertNoContent();

    $tagIds = $user->fresh()->tags->pluck('id')->all();

    expect($tagIds)->toContain($tagA->id)
        ->and($tagIds)->toContain($tagB2->id)
        ->and($tagIds)->not->toContain($tagB1->id);
});

test('rejects a tag that does not belong to the given library', function () {
    $admin = User::factory()->superAdmin()->create();
    $libraryA = makeTagAssignLibrary();
    $libraryB = makeTagAssignLibrary();
    $tagB = Tag::factory()->for($libraryB)->create();

    $user = User::factory()->create();
    $user->libraries()->attach($libraryA->id);

    $this->actingAs($admin)->putJson("/api/v1/users/{$user->id}/tags", [
        'library_id' => $libraryA->id,
        'tag_ids' => [$tagB->id],
    ], tagAssignHeaders())
        ->assertStatus(422);

    expect($user->fresh()->tags->count())->toBe(0);
});

test('rejects assignment when user is not a member of the library', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeTagAssignLibrary();
    $tag = Tag::factory()->for($library)->create();

    $user = User::factory()->create();

    $this->actingAs($admin)->putJson("/api/v1/users/{$user->id}/tags", [
        'library_id' => $library->id,
        'tag_ids' => [$tag->id],
    ], tagAssignHeaders())
        ->assertStatus(422);
});

test('non superadmin cannot assign tags', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $library = makeTagAssignLibrary();

    $user = User::factory()->create();

    $this->actingAs($libraryAdmin)->putJson("/api/v1/users/{$user->id}/tags", [
        'library_id' => $library->id,
        'tag_ids' => [],
    ], tagAssignHeaders())
        ->assertForbidden();
});
