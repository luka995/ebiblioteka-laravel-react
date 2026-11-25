<?php

use App\Enums\LibraryRole;
use App\Models\Author;
use App\Models\Category;
use App\Models\Library;
use App\Models\User;

function catAuthorHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function makeCatAuthorLibrary(): Library
{
    return Library::factory()->create();
}

function makeCatAuthorAdmin(Library $library): User
{
    $admin = User::factory()->create();
    $admin->libraries()->attach($library->id, ['role' => LibraryRole::LibraryAdmin->value]);

    return $admin;
}

test('library admin can create a category and an author in their active library', function () {
    $library = makeCatAuthorLibrary();
    $admin = makeCatAuthorAdmin($library);

    $this->actingAs($admin)->postJson('/api/v1/categories', ['name' => 'Lektira'], catAuthorHeaders())
        ->assertCreated()
        ->assertJsonPath('data.library_id', $library->id);

    $this->actingAs($admin)->postJson('/api/v1/authors', ['name' => 'Branko Ćopić'], catAuthorHeaders())
        ->assertCreated()
        ->assertJsonPath('data.library_id', $library->id);
});

test('category creation reuses an existing category with the same canonical name', function () {
    $library = makeCatAuthorLibrary();
    $admin = makeCatAuthorAdmin($library);
    $existing = Category::factory()->for($library)->create(['name' => 'Роман']);

    $this->actingAs($admin)->postJson('/api/v1/categories', ['name' => 'Roman'], catAuthorHeaders())
        ->assertOk()
        ->assertJsonPath('data.id', $existing->id)
        ->assertJsonPath('data.name', 'Роман');

    expect(Category::where('library_id', $library->id)->count())->toBe(1);
});

test('category creation keeps categories with the same name under different parents', function () {
    $library = makeCatAuthorLibrary();
    $admin = makeCatAuthorAdmin($library);
    $firstParent = Category::factory()->for($library)->create(['name' => 'Родитељ 1']);
    $secondParent = Category::factory()->for($library)->create(['name' => 'Родитељ 2']);
    Category::factory()->for($library)->create(['name' => 'Роман', 'parent_id' => $firstParent->id]);

    $this->actingAs($admin)->postJson('/api/v1/categories', [
        'name' => 'Roman',
        'parent_id' => $secondParent->id,
    ], catAuthorHeaders())
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Roman')
        ->assertJsonPath('data.parent_id', $secondParent->id);

    expect(Category::where('library_id', $library->id)->whereIn('name', ['Роман', 'Roman'])->count())->toBe(2);
});

test('library admin can list only their own categories and authors', function () {
    $library = makeCatAuthorLibrary();
    $other = makeCatAuthorLibrary();
    $admin = makeCatAuthorAdmin($library);

    $ownCategory = Category::factory()->for($library)->create();
    $foreignCategory = Category::factory()->for($other)->create();
    $ownAuthor = Author::factory()->for($library)->create();
    $foreignAuthor = Author::factory()->for($other)->create();

    $this->actingAs($admin)->getJson('/api/v1/categories', catAuthorHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $ownCategory->id])
        ->assertJsonMissing(['id' => $foreignCategory->id]);

    $this->actingAs($admin)->getJson('/api/v1/authors', catAuthorHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $ownAuthor->id])
        ->assertJsonMissing(['id' => $foreignAuthor->id]);
});

test('library admin cannot update or delete a foreign category or author', function () {
    $library = makeCatAuthorLibrary();
    $admin = makeCatAuthorAdmin($library);

    $foreignCategory = Category::factory()->create();
    $foreignAuthor = Author::factory()->create();

    $this->actingAs($admin)->putJson("/api/v1/categories/{$foreignCategory->id}", ['name' => 'Zabranjeno'], catAuthorHeaders())
        ->assertNotFound();

    $this->actingAs($admin)->deleteJson("/api/v1/categories/{$foreignCategory->id}", [], catAuthorHeaders())
        ->assertNotFound();

    $this->actingAs($admin)->putJson("/api/v1/authors/{$foreignAuthor->id}", ['name' => 'Zabranjeno'], catAuthorHeaders())
        ->assertNotFound();

    $this->actingAs($admin)->deleteJson("/api/v1/authors/{$foreignAuthor->id}", [], catAuthorHeaders())
        ->assertNotFound();
});

test('librarian and regular user cannot access categories or authors', function () {
    $library = Library::factory()->create();
    $librarian = User::factory()->create();
    $librarian->libraries()->attach($library->id, ['role' => LibraryRole::Librarian->value]);
    $regular = User::factory()->create();

    $this->actingAs($librarian)->getJson('/api/v1/categories', catAuthorHeaders())->assertForbidden();
    $this->actingAs($librarian)->getJson('/api/v1/authors', catAuthorHeaders())->assertForbidden();
    $this->actingAs($regular)->getJson('/api/v1/categories', catAuthorHeaders())->assertForbidden();
    $this->actingAs($regular)->getJson('/api/v1/authors', catAuthorHeaders())->assertForbidden();
});
