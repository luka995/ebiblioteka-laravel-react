<?php

use App\Enums\UserRole;
use App\Models\Library;
use App\Models\Place;
use App\Models\Region;
use App\Models\User;
use App\Services\ActiveLibraryService;
use Illuminate\Validation\ValidationException;

function activeLibraryHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function makeLibrary(string $name): Library
{
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();

    return Library::factory()->for($place)->create(['name' => $name]);
}

test('selectable returns all non-deleted libraries for superadmin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $active = makeLibrary('Aktivna');
    $deleted = makeLibrary('Obrisana');
    $deleted->update(['deleted' => true]);

    $ids = app(ActiveLibraryService::class)->selectable($superAdmin)->pluck('id')->all();

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($deleted->id);
});

test('selectable returns only pivot libraries for non-superadmin', function () {
    $user = User::factory()->role(UserRole::LibraryAdmin)->create();
    $own = makeLibrary('Moja');
    $other = makeLibrary('Tudja');
    $user->libraries()->attach($own->id);

    $ids = app(ActiveLibraryService::class)->selectable($user)->pluck('id')->all();

    expect($ids)->toContain($own->id)
        ->and($ids)->not->toContain($other->id);
});

test('resolve auto-selects single library for non-superadmin', function () {
    $user = User::factory()->role(UserRole::Librarian)->create();
    $library = makeLibrary('Jedina');
    $user->libraries()->attach($library->id);

    $resolved = app(ActiveLibraryService::class)->resolve($user);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($library->id);
});

test('resolve returns null for superadmin without selection', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    expect(app(ActiveLibraryService::class)->resolve($superAdmin))->toBeNull();
});

test('resolve returns null for non-superadmin with multiple libraries and no selection', function () {
    $user = User::factory()->role(UserRole::LibraryAdmin)->create();
    $user->libraries()->attach(makeLibrary('Prva')->id);
    $user->libraries()->attach(makeLibrary('Druga')->id);

    expect(app(ActiveLibraryService::class)->resolve($user))->toBeNull();
});

test('resolve falls back when stored library was soft deleted', function () {
    $user = User::factory()->role(UserRole::LibraryAdmin)->create();
    $library = makeLibrary('Obrisana');
    $user->libraries()->attach($library->id);

    $service = app(ActiveLibraryService::class);
    $service->set($user, $library->id);

    $library->update(['deleted' => true]);

    expect($service->resolve($user))->toBeNull();
});

test('set persists selection and clears on null for superadmin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $library = makeLibrary('Izabrana');
    $service = app(ActiveLibraryService::class);

    $service->set($superAdmin, $library->id);
    expect($service->resolve($superAdmin)->id)->toBe($library->id);

    $service->set($superAdmin, null);
    expect($service->resolve($superAdmin))->toBeNull();
});

test('set rejects a library that does not belong to the user', function () {
    $user = User::factory()->role(UserRole::LibraryAdmin)->create();
    $foreign = makeLibrary('Tudja');

    app(ActiveLibraryService::class)->set($user, $foreign->id);
})->throws(ValidationException::class);

test('set rejects null for non-superadmin', function () {
    $user = User::factory()->role(UserRole::LibraryAdmin)->create();

    app(ActiveLibraryService::class)->set($user, null);
})->throws(ValidationException::class);

test('me returns selectable libraries and active library', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $library = makeLibrary('Aktivna');

    app(ActiveLibraryService::class)->set($superAdmin, $library->id);

    $this->actingAs($superAdmin)->getJson('/api/v1/auth/me', activeLibraryHeaders())
        ->assertOk()
        ->assertJsonPath('selectable_libraries.0.id', $library->id)
        ->assertJsonPath('active_library.id', $library->id);
});

test('me auto-selects the only library for a single-library user', function () {
    $user = User::factory()->role(UserRole::Librarian)->create();
    $library = makeLibrary('Jedina');
    $user->libraries()->attach($library->id);

    $this->actingAs($user)->getJson('/api/v1/auth/me', activeLibraryHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'selectable_libraries')
        ->assertJsonPath('active_library.id', $library->id);
});

test('active-library endpoint stores a valid selection', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $library = makeLibrary('Izabrana');

    $this->actingAs($superAdmin)->putJson('/api/v1/auth/active-library', [
        'library_id' => $library->id,
    ], activeLibraryHeaders())->assertNoContent();

    expect(app(ActiveLibraryService::class)->resolve($superAdmin)->id)->toBe($library->id);
});

test('active-library endpoint clears selection for superadmin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $library = makeLibrary('Izabrana');
    app(ActiveLibraryService::class)->set($superAdmin, $library->id);

    $this->actingAs($superAdmin)->putJson('/api/v1/auth/active-library', [
        'library_id' => null,
    ], activeLibraryHeaders())->assertNoContent();

    expect(app(ActiveLibraryService::class)->resolve($superAdmin))->toBeNull();
});

test('active-library endpoint rejects a foreign library', function () {
    $user = User::factory()->role(UserRole::LibraryAdmin)->create();
    $foreign = makeLibrary('Tudja');

    $this->actingAs($user)->putJson('/api/v1/auth/active-library', [
        'library_id' => $foreign->id,
    ], activeLibraryHeaders())->assertUnprocessable();
});

test('active-library endpoint rejects null for non-superadmin', function () {
    $user = User::factory()->role(UserRole::LibraryAdmin)->create();

    $this->actingAs($user)->putJson('/api/v1/auth/active-library', [
        'library_id' => null,
    ], activeLibraryHeaders())->assertUnprocessable();
});

test('users list is scoped to active library', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $libraryA = makeLibrary('Biblioteka A');
    $libraryB = makeLibrary('Biblioteka B');

    $inA = User::factory()->create();
    $inA->libraries()->attach($libraryA->id);
    $inB = User::factory()->create();
    $inB->libraries()->attach($libraryB->id);

    app(ActiveLibraryService::class)->set($superAdmin, $libraryA->id);

    $this->actingAs($superAdmin)->getJson('/api/v1/users', activeLibraryHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $inA->id])
        ->assertJsonMissing(['id' => $inB->id]);
});

test('users list is unscoped when superadmin has no active library', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $libraryA = makeLibrary('Biblioteka A');

    $member = User::factory()->create();
    $member->libraries()->attach($libraryA->id);

    $this->actingAs($superAdmin)->getJson('/api/v1/users', activeLibraryHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $member->id]);
});
