<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Library;
use App\Models\User;

function authorCrudHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function makeAuthorLibrary(): Library
{
    return Library::factory()->create();
}

test('superadmin can list authors paginated with meta', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeAuthorLibrary();
    Author::factory()->count(30)->for($library)->create();

    $this->actingAs($admin)->getJson('/api/v1/authors?per_page=10', authorCrudHeaders())
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
});

test('superadmin author list defaults to the active library and allows an explicit override', function () {
    $admin = User::factory()->superAdmin()->create();
    $active = makeAuthorLibrary();
    $other = makeAuthorLibrary();
    $own = Author::factory()->for($active)->create();
    $foreign = Author::factory()->for($other)->create();
    $request = $this->actingAs($admin)->withSession(['active_library_id' => $active->id]);

    $request->getJson('/api/v1/authors', authorCrudHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $own->id])
        ->assertJsonMissing(['id' => $foreign->id]);

    $request->getJson("/api/v1/authors?library_id={$other->id}", authorCrudHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $foreign->id])
        ->assertJsonMissing(['id' => $own->id]);
});

test('superadmin can create update and delete an author', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeAuthorLibrary();

    $this->actingAs($admin)->postJson('/api/v1/authors', ['name' => 'Ivo Andrić', 'library_id' => $library->id], authorCrudHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Ivo Andrić')
        ->assertJsonPath('data.display_name', 'Andrić, Ivo')
        ->assertJsonPath('data.library_id', $library->id);

    $author = Author::where('name', 'Ivo Andrić')->firstOrFail();

    $this->actingAs($admin)->putJson("/api/v1/authors/{$author->id}", ['name' => 'Ivo Andrić (Nobelovac)'], authorCrudHeaders())
        ->assertOk()
        ->assertJsonPath('data.name', 'Ivo Andrić (Nobelovac)')
        ->assertJsonPath('data.display_name', '(Nobelovac), Ivo Andrić');

    $this->actingAs($admin)->deleteJson("/api/v1/authors/{$author->id}", [], authorCrudHeaders())
        ->assertNoContent();

    expect(Author::find($author->id))->toBeNull();
});

test('author display name preserves comma-formatted names and formats classic names only for display', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeAuthorLibrary();

    Author::factory()->for($library)->create(['name' => 'Andrić, Ivo']);
    Author::factory()->for($library)->create(['name' => 'Petar Marko Petrović']);

    $response = $this->actingAs($admin)->getJson('/api/v1/authors', authorCrudHeaders());

    $response->assertOk()
        ->assertJsonFragment(['name' => 'Andrić, Ivo', 'display_name' => 'Andrić, Ivo'])
        ->assertJsonFragment(['name' => 'Petar Marko Petrović', 'display_name' => 'Petrović, Petar Marko']);

    expect(Author::where('name', 'Petar Marko Petrović')->exists())->toBeTrue();
});

test('author name must be unique per library but can repeat across libraries', function () {
    $admin = User::factory()->superAdmin()->create();
    $libraryA = makeAuthorLibrary();
    $libraryB = makeAuthorLibrary();

    $this->actingAs($admin)->postJson('/api/v1/authors', ['name' => 'Miloš Crnjanski', 'library_id' => $libraryA->id], authorCrudHeaders())
        ->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/authors', ['name' => 'Miloš Crnjanski', 'library_id' => $libraryA->id], authorCrudHeaders())
        ->assertUnprocessable();

    $this->actingAs($admin)->postJson('/api/v1/authors', ['name' => 'Miloš Crnjanski', 'library_id' => $libraryB->id], authorCrudHeaders())
        ->assertCreated();
});

test('author creation rejects a canonical duplicate in the same library', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeAuthorLibrary();
    Author::factory()->for($library)->create(['name' => 'Иво Андрић']);

    $this->actingAs($admin)->postJson('/api/v1/authors', ['name' => 'Andric Ivo', 'library_id' => $library->id], authorCrudHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect(Author::where('library_id', $library->id)->count())->toBe(1);
});

test('author update rejects a canonical duplicate in the same library', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeAuthorLibrary();
    Author::factory()->for($library)->create(['name' => 'Иво Андрић']);
    $author = Author::factory()->for($library)->create(['name' => 'Милош Црњански']);

    $this->actingAs($admin)->putJson("/api/v1/authors/{$author->id}", ['name' => 'Andric Ivo'], authorCrudHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect(Author::where('library_id', $library->id)->count())->toBe(2);
});

test('author linked to a book cannot be deleted', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeAuthorLibrary();
    $author = Author::factory()->for($library)->create();
    $book = Book::factory()->for($library)->create();
    $book->authors()->attach($author->id);

    $this->actingAs($admin)->deleteJson("/api/v1/authors/{$author->id}", [], authorCrudHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('message', __('validation.custom.author_has_books'));

    expect(Author::find($author->id))->not->toBeNull()
        ->and($book->authors()->whereKey($author->id)->exists())->toBeTrue();
});

test('superadmin must provide a library when creating an author', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->postJson('/api/v1/authors', ['name' => 'Bez biblioteke'], authorCrudHeaders())
        ->assertUnprocessable();
});

test('regular user cannot access authors', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/authors', authorCrudHeaders())
        ->assertForbidden();
});
