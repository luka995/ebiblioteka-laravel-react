<?php

use App\Models\Library;
use App\Models\Place;
use App\Models\Region;
use App\Models\User;

function crudApiHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

test('superadmin can show a user with full profile', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create([
        'jmbg' => '0101990711234',
        'address' => 'Kralja Petra 5',
        'city' => 'Beograd',
        'post_code' => '11000',
    ]);

    $this->actingAs($admin)->getJson("/api/v1/users/{$user->id}", crudApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.jmbg', '0101990711234')
        ->assertJsonPath('data.city', 'Beograd');
});

test('non superadmin cannot show or mutate a user', function () {
    $libraryAdmin = User::factory()->role(\App\Enums\UserRole::LibraryAdmin)->create();
    $target = User::factory()->create();

    $this->actingAs($libraryAdmin)
        ->getJson("/api/v1/users/{$target->id}", crudApiHeaders())
        ->assertForbidden();
});

test('superadmin can update a user without changing password', function () {
    $admin = User::factory()->superAdmin()->create();
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();
    $library = Library::factory()->for($place)->create();

    $user = User::factory()->create(['email' => 'before@example.com']);

    $this->actingAs($admin)->putJson("/api/v1/users/{$user->id}", [
        'first_name' => 'Ana',
        'last_name' => 'Anić',
        'username' => 'ana.anic',
        'email' => 'after@example.com',
        'role' => 'librarian',
        'libraries' => [$library->id],
    ], crudApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Ana')
        ->assertJsonPath('data.role', 'librarian')
        ->assertJsonPath('data.libraries.0.id', $library->id);

    $fresh = $user->fresh();

    expect($fresh->email)->toBe('after@example.com')
        ->and($fresh->password)->toBe($user->password)
        ->and($fresh->libraries->pluck('id')->all())->toContain($library->id);
});

test('superadmin can soft delete a user', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => '1000000000123']);

    $this->actingAs($admin)->deleteJson("/api/v1/users/{$user->id}", [], crudApiHeaders())
        ->assertNoContent();

    expect(User::find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id)->deleted_at)->not->toBeNull();

    $this->actingAs($admin)->getJson("/api/v1/users", crudApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $admin->id);
});

test('user cannot delete their own account', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->deleteJson("/api/v1/users/{$admin->id}", [], crudApiHeaders())
        ->assertStatus(422);

    expect(User::find($admin->id))->not->toBeNull();
});

test('users list is paginated with meta', function () {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->count(30)->create();

    $this->actingAs($admin)->getJson('/api/v1/users?per_page=10', crudApiHeaders())
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 31)
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
});

test('superadmin can show, update and soft delete a library', function () {
    $admin = User::factory()->superAdmin()->create();
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();
    $library = Library::factory()->for($place)->create(['name' => 'Stara biblioteka']);

    $this->actingAs($admin)->getJson("/api/v1/libraries/{$library->id}", crudApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.name', 'Stara biblioteka');

    $this->actingAs($admin)->putJson("/api/v1/libraries/{$library->id}", [
        'name' => 'Nova biblioteka',
        'address' => 'Novi put 1',
        'place_id' => $place->id,
        'work_time' => 'Pon - Pet, 08-20',
    ], crudApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.name', 'Nova biblioteka');

    $this->actingAs($admin)->deleteJson("/api/v1/libraries/{$library->id}", [], crudApiHeaders())
        ->assertNoContent();

    expect($library->fresh()->deleted)->toBeTrue();

    $this->actingAs($admin)->getJson('/api/v1/libraries', crudApiHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($admin)->getJson("/api/v1/libraries/{$library->id}", crudApiHeaders())
        ->assertNotFound();
});

test('superadmin can change another user password without current password', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $originalPassword = $user->password;

    $this->actingAs($admin)->putJson("/api/v1/users/{$user->id}/password", [
        'password' => 'newsecret123',
        'password_confirmation' => 'newsecret123',
    ], crudApiHeaders())
        ->assertNoContent();

    expect($user->fresh()->password)->not->toBe($originalPassword);
});

test('non superadmin cannot change another user password', function () {
    $libraryAdmin = User::factory()->role(\App\Enums\UserRole::LibraryAdmin)->create();
    $target = User::factory()->create();

    $this->actingAs($libraryAdmin)->putJson("/api/v1/users/{$target->id}/password", [
        'password' => 'newsecret123',
        'password_confirmation' => 'newsecret123',
    ], crudApiHeaders())
        ->assertForbidden();
});
