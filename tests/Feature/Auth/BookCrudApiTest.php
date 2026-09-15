<?php

use App\Enums\UserRole;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Category;
use App\Models\Library;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function bookHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function makeBookLibrary(): Library
{
    return Library::factory()->create();
}

function makeBookStaff(Library $library, UserRole $role = UserRole::Librarian): User
{
    $user = User::factory()->role($role)->create();
    $user->libraries()->attach($library->id);

    return $user;
}

test('superadmin can list books paginated with meta', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeBookLibrary();
    Book::factory()->count(30)->for($library)->create();

    $this->actingAs($admin)->getJson('/api/v1/books?per_page=10', bookHeaders())
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
});

test('superadmin can create a book with authors and categories resolved', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeBookLibrary();
    $primary = Category::factory()->for($library)->create(['name' => 'Lektira']);
    $secondary = Category::factory()->for($library)->create(['name' => 'Romani']);

    $response = $this->actingAs($admin)->postJson('/api/v1/books', [
        'name' => 'Orlovi rano lete',
        'library_id' => $library->id,
        'category_primary_id' => $primary->id,
        'category_secondary_id' => $secondary->id,
        'description' => 'Dečji roman.',
        'authors' => ['Branko Ćopić', 'Branko Ćopić'],
    ], bookHeaders());

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Orlovi rano lete')
        ->assertJsonPath('data.slug', 'orlovi-rano-lete')
        ->assertJsonPath('data.category_primary_name', 'Lektira')
        ->assertJsonCount(1, 'data.authors')
        ->assertJsonPath('data.authors.0.name', 'Branko Ćopić');

    expect(Author::where('library_id', $library->id)->where('name', 'Branko Ćopić')->count())->toBe(1);
});

test('book category must belong to the same library', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeBookLibrary();
    $other = makeBookLibrary();
    $foreignCategory = Category::factory()->for($other)->create();

    $this->actingAs($admin)->postJson('/api/v1/books', [
        'name' => 'Nedozvoljena kategorija',
        'library_id' => $library->id,
        'category_primary_id' => $foreignCategory->id,
    ], bookHeaders())->assertUnprocessable();
});

test('superadmin can update and delete a book', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeBookLibrary();
    $book = Book::factory()->for($library)->create(['name' => 'Staro ime']);

    $this->actingAs($admin)->putJson("/api/v1/books/{$book->id}", [
        'name' => 'Novo ime',
        'authors' => ['Ivo Andrić'],
    ], bookHeaders())
        ->assertOk()
        ->assertJsonPath('data.name', 'Novo ime')
        ->assertJsonPath('data.authors.0.name', 'Ivo Andrić');

    $this->actingAs($admin)->deleteJson("/api/v1/books/{$book->id}", [], bookHeaders())
        ->assertNoContent();

    expect(Book::find($book->id))->toBeNull()
        ->and(Book::withTrashed()->find($book->id))->not->toBeNull();
});

test('librarian can manage books within their library', function () {
    $library = makeBookLibrary();
    $librarian = makeBookStaff($library);

    $this->actingAs($librarian)->postJson('/api/v1/books', ['name' => 'Bibliotekarska knjiga'], bookHeaders())
        ->assertCreated()
        ->assertJsonPath('data.library_id', $library->id);
});

test('librarian sees only books of the active library', function () {
    $library = makeBookLibrary();
    $other = makeBookLibrary();
    $librarian = makeBookStaff($library);

    $own = Book::factory()->for($library)->create(['name' => 'Sopstvena']);
    $foreign = Book::factory()->for($other)->create(['name' => 'Đačka']);

    $this->actingAs($librarian)->getJson('/api/v1/books', bookHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $own->id])
        ->assertJsonMissing(['id' => $foreign->id]);
});

test('regular user cannot access books', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/books', bookHeaders())->assertForbidden();
    $this->actingAs($user)->postJson('/api/v1/books', ['name' => 'X'], bookHeaders())->assertForbidden();
});

test('superadmin can view the archive, restore and permanently delete a book', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeBookLibrary();
    $book = Book::factory()->for($library)->create(['name' => 'Arhivirana knjiga']);
    $book->delete();

    $this->actingAs($admin)->getJson('/api/v1/books/archive', bookHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $book->id]);

    $this->actingAs($admin)->postJson("/api/v1/books/{$book->id}/restore", [], bookHeaders())
        ->assertOk()
        ->assertJsonPath('data.deleted_at', null);

    $book->delete();

    $this->actingAs($admin)->deleteJson("/api/v1/books/{$book->id}/force", [], bookHeaders())
        ->assertNoContent();

    expect(Book::withTrashed()->find($book->id))->toBeNull();
});

test('superadmin can create a book with copies atomically', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeBookLibrary();

    $this->actingAs($admin)->postJson('/api/v1/books', [
        'name' => 'Knjiga sa kopijama',
        'library_id' => $library->id,
        'with_copies' => true,
        'copies' => 3,
        'isbn' => '978-86-81131-21-3',
        'publisher' => 'Zavod',
        'publish_place' => 'Beograd',
        'publish_year' => '2018',
        'num_of_pages' => 320,
        'dimension' => '21 cm',
        'udk' => '821.163.41',
    ], bookHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Knjiga sa kopijama')
        ->assertJsonPath('data.copies_count', 3);

    $book = Book::where('name', 'Knjiga sa kopijama')->firstOrFail();
    $copies = BookCopy::where('book_id', $book->id)->orderBy('order_number')->get();

    expect($copies)->toHaveCount(3)
        ->and($copies->pluck('order_number')->all())->toBe(['1', '2', '3'])
        ->and($copies->first()->publisher)->toBe('Zavod')
        ->and($copies->first()->publish_place)->toBe('Beograd')
        ->and($copies->first()->num_of_pages)->toBe(320)
        ->and($copies->first()->udk)->toBe('821.163.41')
        ->and($copies->first()->isbn)->toBe('978-86-81131-21-3');
});

test('superadmin can create a book without copies', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeBookLibrary();

    $this->actingAs($admin)->postJson('/api/v1/books', [
        'name' => 'Samo naslov',
        'library_id' => $library->id,
    ], bookHeaders())
        ->assertCreated()
        ->assertJsonPath('data.copies_count', 0);

    expect(BookCopy::count())->toBe(0);
});

test('manual library creates a single copy with the provided order number', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create(['inv_number_auto' => false]);

    $this->actingAs($admin)->postJson('/api/v1/books', [
        'name' => 'Rucna knjiga',
        'library_id' => $library->id,
        'with_copies' => true,
        'order_number' => '1',
    ], bookHeaders())
        ->assertCreated()
        ->assertJsonPath('data.copies_count', 1);

    $book = Book::where('name', 'Rucna knjiga')->firstOrFail();

    expect(BookCopy::where('book_id', $book->id)->value('order_number'))->toBe('1');
});

test('copies validation follows the library inventory mode', function () {
    $admin = User::factory()->superAdmin()->create();
    $auto = makeBookLibrary();
    $manual = Library::factory()->create(['inv_number_auto' => false]);

    $this->actingAs($admin)->postJson('/api/v1/books', [
        'name' => 'Bez kolicine',
        'library_id' => $auto->id,
        'with_copies' => true,
    ], bookHeaders())->assertUnprocessable()->assertJsonValidationErrors('copies');

    $this->actingAs($admin)->postJson('/api/v1/books', [
        'name' => 'Bez broja',
        'library_id' => $manual->id,
        'with_copies' => true,
    ], bookHeaders())->assertUnprocessable()->assertJsonValidationErrors('order_number');
});

test('atomic book creation rolls back when inventory has a discrepancy', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeBookLibrary();
    $book = Book::factory()->for($library)->create();
    BookCopy::factory()->for($library)->for($book)->create(['order_number' => '2']);
    BookCopy::factory()->for($library)->for($book)->create(['order_number' => '9'])->delete();

    $this->actingAs($admin)->postJson('/api/v1/books', [
        'name' => 'Ne treba da nastane',
        'library_id' => $library->id,
        'with_copies' => true,
        'copies' => 1,
    ], bookHeaders())
        ->assertStatus(409)
        ->assertJsonStructure(['inventory_discrepancy']);

    expect(Book::where('name', 'Ne treba da nastane')->exists())->toBeFalse();
});

test('staff can upload a book cover and the book exposes a local image url', function () {
    Storage::fake('public');

    $admin = User::factory()->superAdmin()->create();
    $library = makeBookLibrary();
    $book = Book::factory()->for($library)->create();

    $upload = $this->actingAs($admin)->post('/api/v1/books/upload-cover', [
        'image' => UploadedFile::fake()->create('korica.jpg', 100, 'image/jpeg'),
    ], bookHeaders());

    $upload->assertOk()->assertJsonStructure(['path', 'url']);

    $path = $upload->json('path');
    Storage::disk('public')->assertExists($path);

    $this->actingAs($admin)->putJson("/api/v1/books/{$book->id}", [
        'name' => $book->name,
        'image' => $path,
    ], bookHeaders())
        ->assertOk()
        ->assertJsonPath('data.image', $path)
        ->assertJsonPath('data.image_url', '/storage/'.$path);
});

test('book cover upload rejects non-image files', function () {
    Storage::fake('public');

    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->post('/api/v1/books/upload-cover', [
        'image' => UploadedFile::fake()->create('dokument.pdf', 100, 'application/pdf'),
    ], bookHeaders())->assertUnprocessable()->assertJsonValidationErrors('image');
});
