<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Library;
use App\Models\User;
use App\Services\ActiveLibraryService;

function categoryCrudHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function makeCategoryLibrary(): Library
{
    return Library::factory()->create();
}

test('superadmin can list categories paginated with meta', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeCategoryLibrary();
    Category::factory()->count(30)->for($library)->create();

    app(ActiveLibraryService::class)->set($admin, $library->id);

    $this->actingAs($admin)->getJson('/api/v1/categories?per_page=10', categoryCrudHeaders())
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
});

test('superadmin can create update and delete a category', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeCategoryLibrary();

    $this->actingAs($admin)->postJson('/api/v1/categories', ['name' => 'Lektira', 'library_id' => $library->id], categoryCrudHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Lektira')
        ->assertJsonPath('data.library_id', $library->id)
        ->assertJsonPath('data.parent_id', null);

    $category = Category::where('name', 'Lektira')->firstOrFail();

    $this->actingAs($admin)->putJson("/api/v1/categories/{$category->id}", ['name' => 'Lektira za osnovnu'], categoryCrudHeaders())
        ->assertOk()
        ->assertJsonPath('data.name', 'Lektira za osnovnu');

    $this->actingAs($admin)->deleteJson("/api/v1/categories/{$category->id}", [], categoryCrudHeaders())
        ->assertNoContent();

    expect(Category::find($category->id))->toBeNull();
});

test('categories support unlimited nesting depth', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeCategoryLibrary();

    $this->actingAs($admin)->postJson('/api/v1/categories', ['name' => '9', 'library_id' => $library->id], categoryCrudHeaders())
        ->assertCreated();
    $level1 = Category::where('name', '9')->firstOrFail();

    $this->actingAs($admin)->postJson('/api/v1/categories', ['name' => '91', 'library_id' => $library->id, 'parent_id' => $level1->id], categoryCrudHeaders())
        ->assertCreated();
    $level2 = Category::where('name', '91')->firstOrFail();

    $this->actingAs($admin)->postJson('/api/v1/categories', ['name' => '913', 'library_id' => $library->id, 'parent_id' => $level2->id], categoryCrudHeaders())
        ->assertCreated()
        ->assertJsonPath('data.full_name', '9 / 91 / 913');
});

test('a category cannot become its own parent or descendant', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeCategoryLibrary();

    $parent = Category::factory()->for($library)->create(['name' => 'Roditelj']);
    $child = Category::factory()->childOf($parent)->create(['name' => 'Dete']);

    $this->actingAs($admin)->putJson("/api/v1/categories/{$parent->id}", ['name' => 'Roditelj', 'parent_id' => $child->id], categoryCrudHeaders())
        ->assertUnprocessable();

    $this->actingAs($admin)->putJson("/api/v1/categories/{$parent->id}", ['name' => 'Roditelj', 'parent_id' => $parent->id], categoryCrudHeaders())
        ->assertUnprocessable();
});

test('parent category must belong to the same library', function () {
    $admin = User::factory()->superAdmin()->create();
    $libraryA = makeCategoryLibrary();
    $libraryB = makeCategoryLibrary();

    $foreignParent = Category::factory()->for($libraryB)->create();

    $this->actingAs($admin)->postJson('/api/v1/categories', ['name' => 'Dete', 'library_id' => $libraryA->id, 'parent_id' => $foreignParent->id], categoryCrudHeaders())
        ->assertUnprocessable();
});

test('category with children cannot be deleted', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeCategoryLibrary();

    $parent = Category::factory()->for($library)->create();
    Category::factory()->childOf($parent)->create();

    $this->actingAs($admin)->deleteJson("/api/v1/categories/{$parent->id}", [], categoryCrudHeaders())
        ->assertUnprocessable();

    expect(Category::find($parent->id))->not->toBeNull();
});

test('superadmin must provide a library when creating a category', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->postJson('/api/v1/categories', ['name' => 'Bez biblioteke'], categoryCrudHeaders())
        ->assertUnprocessable();
});

test('regular user cannot access categories', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/categories', categoryCrudHeaders())
        ->assertForbidden();
});

test('category search matches both cyrillic and latin scripts', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeCategoryLibrary();
    Category::factory()->for($library)->create(['name' => 'Андрић']);
    Category::factory()->for($library)->create(['name' => 'Petar']);

    app(ActiveLibraryService::class)->set($admin, $library->id);

    $this->actingAs($admin)
        ->getJson('/api/v1/categories?all=1&library_id='.$library->id.'&search='.urlencode('Andrić'), categoryCrudHeaders())
        ->assertOk()
        ->assertJsonFragment(['name' => 'Андрић']);

    $this->actingAs($admin)
        ->getJson('/api/v1/categories?all=1&library_id='.$library->id.'&search='.urlencode('Петар'), categoryCrudHeaders())
        ->assertOk()
        ->assertJsonFragment(['name' => 'Petar']);
});

test('superadmin categories list is scoped to the active library', function () {
    $admin = User::factory()->superAdmin()->create();
    $libraryA = makeCategoryLibrary();
    $libraryB = makeCategoryLibrary();
    $inA = Category::factory()->for($libraryA)->create(['name' => 'A kategorija']);
    $inB = Category::factory()->for($libraryB)->create(['name' => 'B kategorija']);

    app(ActiveLibraryService::class)->set($admin, $libraryA->id);

    $this->actingAs($admin)->getJson('/api/v1/categories?all=1', categoryCrudHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $inA->id])
        ->assertJsonMissing(['id' => $inB->id]);
});

test('superadmin must have an active library to list categories', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeCategoryLibrary();
    Category::factory()->for($library)->create(['name' => 'Sve kategorije']);

    $this->actingAs($admin)->getJson('/api/v1/categories?all=1', categoryCrudHeaders())
        ->assertStatus(422);
});

test('explicit library_id overrides the active library for superadmin', function () {
    $admin = User::factory()->superAdmin()->create();
    $libraryA = makeCategoryLibrary();
    $libraryB = makeCategoryLibrary();
    $inA = Category::factory()->for($libraryA)->create(['name' => 'A kategorija']);
    $inB = Category::factory()->for($libraryB)->create(['name' => 'B kategorija']);

    app(ActiveLibraryService::class)->set($admin, $libraryA->id);

    $this->actingAs($admin)->getJson('/api/v1/categories?all=1&library_id='.$libraryB->id, categoryCrudHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $inB->id])
        ->assertJsonMissing(['id' => $inA->id]);
});

test('non-superadmin cannot override the active library with a foreign library_id', function () {
    $user = User::factory()->role(UserRole::LibraryAdmin)->create();
    $libraryA = makeCategoryLibrary();
    $libraryB = makeCategoryLibrary();
    $user->libraries()->attach($libraryA->id);
    $inA = Category::factory()->for($libraryA)->create(['name' => 'A kategorija']);
    $inB = Category::factory()->for($libraryB)->create(['name' => 'B kategorija']);

    app(ActiveLibraryService::class)->set($user, $libraryA->id);

    $this->actingAs($user)->getJson('/api/v1/categories?all=1&library_id='.$libraryB->id, categoryCrudHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $inA->id])
        ->assertJsonMissing(['id' => $inB->id]);
});
