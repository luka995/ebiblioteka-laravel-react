<?php

use App\Models\Library;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use App\Models\User;
use App\Services\ActiveLibraryService;

function tagCrudHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function makeTagLibrary(): Library
{
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();

    return Library::factory()->for($place)->create();
}

test('superadmin can list tags paginated with meta', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeTagLibrary();
    Tag::factory()->count(30)->for($library)->create();

    app(ActiveLibraryService::class)->set($admin, $library->id);

    $this->actingAs($admin)->getJson('/api/v1/tags?per_page=10', tagCrudHeaders())
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
});

test('superadmin must have an active library to list tags', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeTagLibrary();
    Tag::factory()->for($library)->create();

    $this->actingAs($admin)->getJson('/api/v1/tags', tagCrudHeaders())
        ->assertStatus(422);
});

test('superadmin sees only tags of the active library', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeTagLibrary();
    $other = makeTagLibrary();
    $this->withSession(['active_library_id' => $library->id]);

    $own = Tag::factory()->for($library)->create();
    Tag::factory()->for($other)->create();

    $this->actingAs($admin)->getJson('/api/v1/tags', tagCrudHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id);
});

test('explicit library filter overrides the active library for tags', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeTagLibrary();
    $other = makeTagLibrary();
    $this->withSession(['active_library_id' => $library->id]);

    Tag::factory()->for($library)->create();
    $foreign = Tag::factory()->for($other)->create();

    $this->actingAs($admin)->getJson('/api/v1/tags?library_id='.$other->id, tagCrudHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $foreign->id);
});

test('superadmin can create update and delete a tag', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeTagLibrary();

    $this->actingAs($admin)->postJson('/api/v1/tags', ['name' => 'Redovni', 'library_id' => $library->id], tagCrudHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Redovni')
        ->assertJsonPath('data.library_id', $library->id);

    $tag = Tag::where('name', 'Redovni')->firstOrFail();

    $this->actingAs($admin)->putJson("/api/v1/tags/{$tag->id}", ['name' => 'Vip', 'library_id' => $library->id], tagCrudHeaders())
        ->assertOk()
        ->assertJsonPath('data.name', 'Vip');

    $this->actingAs($admin)->deleteJson("/api/v1/tags/{$tag->id}", [], tagCrudHeaders())
        ->assertNoContent();

    expect(Tag::find($tag->id))->toBeNull();
});

test('tag name must be unique per library but can repeat across libraries', function () {
    $admin = User::factory()->superAdmin()->create();
    $libraryA = makeTagLibrary();
    $libraryB = makeTagLibrary();

    $this->actingAs($admin)->postJson('/api/v1/tags', ['name' => 'Član', 'library_id' => $libraryA->id], tagCrudHeaders())
        ->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/tags', ['name' => 'Član', 'library_id' => $libraryA->id], tagCrudHeaders())
        ->assertStatus(422);

    $this->actingAs($admin)->postJson('/api/v1/tags', ['name' => 'Član', 'library_id' => $libraryB->id], tagCrudHeaders())
        ->assertCreated();
});

test('regular user cannot access tags', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/tags', tagCrudHeaders())
        ->assertForbidden();
});
