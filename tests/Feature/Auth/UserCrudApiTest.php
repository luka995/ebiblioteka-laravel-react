<?php

use App\Enums\UserRole;
use App\Models\Library;
use App\Models\Place;
use App\Models\Region;
use App\Models\User;
use App\Support\BarCode;

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
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
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

    $user = User::factory()->create([
        'email' => 'before@example.com',
        'bar_code' => '0000000001236',
    ]);

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
        ->and($fresh->bar_code)->toBe('0000000001236')
        ->and($fresh->libraries->pluck('id')->all())->toContain($library->id);
});

test('superadmin can regenerate a users barcode explicitly', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => '0000000001236']);

    $this->actingAs($admin)->putJson("/api/v1/users/{$user->id}", [
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->email,
        'role' => $user->role->value,
        'regenerate_barcode' => true,
    ], crudApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.bar_code', fn ($barcode) => BarCode::validate($barcode));

    expect($user->fresh()->bar_code)->not->toBe('0000000001236')
        ->and(BarCode::validate($user->fresh()->bar_code))->toBeTrue();
});

test('superadmin can soft delete a user', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => '1000000000123']);

    $this->actingAs($admin)->deleteJson("/api/v1/users/{$user->id}", [], crudApiHeaders())
        ->assertNoContent();

    expect(User::find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id)->deleted_at)->not->toBeNull();

    $this->actingAs($admin)->getJson('/api/v1/users', crudApiHeaders())
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
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $target = User::factory()->create();

    $this->actingAs($libraryAdmin)->putJson("/api/v1/users/{$target->id}/password", [
        'password' => 'newsecret123',
        'password_confirmation' => 'newsecret123',
    ], crudApiHeaders())
        ->assertForbidden();
});

test('library admin cannot soft delete or force delete a user even when sharing a library', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $library = Library::factory()->create();
    $libraryAdmin->libraries()->attach($library->id);
    $target = User::factory()->create();
    $target->libraries()->attach($library->id);

    $this->actingAs($libraryAdmin)->deleteJson("/api/v1/users/{$target->id}", [], crudApiHeaders())
        ->assertForbidden();

    $this->actingAs($libraryAdmin)->deleteJson("/api/v1/users/{$target->id}/force", [], crudApiHeaders())
        ->assertForbidden();

    expect(User::find($target->id))->not->toBeNull();
});

test('library admin editing a user keeps memberships in libraries it does not manage', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $managed = Library::factory()->create();
    $libraryAdmin->libraries()->attach($managed->id);
    $foreign = Library::factory()->create();
    $target = User::factory()->create(['email' => 'clan@example.com']);
    $target->libraries()->attach([$managed->id, $foreign->id]);

    $this->actingAs($libraryAdmin)->putJson("/api/v1/users/{$target->id}", [
        'first_name' => 'Ana',
        'last_name' => 'Anić',
        'username' => 'ana.anic',
        'email' => 'clan@example.com',
        'role' => 'user',
        'libraries' => [$managed->id],
    ], crudApiHeaders())->assertOk();

    expect($target->fresh()->libraries()->pluck('libraries.id')->all())
        ->toContain($managed->id)
        ->toContain($foreign->id);
});

test('library admin editing a user can remove it from a managed library but not from others', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $managed = Library::factory()->create();
    $libraryAdmin->libraries()->attach($managed->id);
    $foreign = Library::factory()->create();
    $target = User::factory()->create(['email' => 'clan2@example.com']);
    $target->libraries()->attach([$managed->id, $foreign->id]);

    $this->actingAs($libraryAdmin)->putJson("/api/v1/users/{$target->id}", [
        'first_name' => 'Ana',
        'last_name' => 'Anić',
        'username' => 'ana.anic2',
        'email' => 'clan2@example.com',
        'role' => 'user',
        'libraries' => [],
    ], crudApiHeaders())->assertOk();

    $ids = $target->fresh()->libraries()->pluck('libraries.id')->all();

    expect($ids)->toContain($foreign->id)
        ->and($ids)->not->toContain($managed->id);
});

test('superadmin can bulk soft delete accounts', function () {
    $admin = User::factory()->superAdmin()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($admin)->postJson('/api/v1/users/bulk/deactivate', [
        'user_ids' => [$first->id, $second->id],
    ], crudApiHeaders())->assertNoContent();

    expect(User::find($first->id))->toBeNull()
        ->and(User::find($second->id))->toBeNull()
        ->and(User::withTrashed()->find($first->id))->not->toBeNull();
});

test('superadmin can bulk force delete accounts', function () {
    $admin = User::factory()->superAdmin()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($admin)->deleteJson('/api/v1/users/bulk/force', [
        'user_ids' => [$first->id, $second->id],
    ], crudApiHeaders())->assertNoContent();

    expect(User::withTrashed()->find($first->id))->toBeNull()
        ->and(User::withTrashed()->find($second->id))->toBeNull();
});

test('bulk account deletion refuses to delete own account', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->postJson('/api/v1/users/bulk/deactivate', [
        'user_ids' => [$admin->id],
    ], crudApiHeaders())->assertStatus(422);

    expect(User::find($admin->id))->not->toBeNull();
});

test('library admin cannot bulk soft or force delete accounts', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $library = Library::factory()->create();
    $libraryAdmin->libraries()->attach($library->id);
    $target = User::factory()->create();
    $target->libraries()->attach($library->id);

    $this->actingAs($libraryAdmin)->postJson('/api/v1/users/bulk/deactivate', [
        'user_ids' => [$target->id],
    ], crudApiHeaders())->assertForbidden();

    $this->actingAs($libraryAdmin)->deleteJson('/api/v1/users/bulk/force', [
        'user_ids' => [$target->id],
    ], crudApiHeaders())->assertForbidden();

    expect(User::find($target->id))->not->toBeNull();
});
