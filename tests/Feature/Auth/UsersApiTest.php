<?php

use App\Enums\UserRole;
use App\Models\Library;
use App\Models\Place;
use App\Models\Region;
use App\Models\User;
use App\Services\ActiveLibraryService;
use App\Support\BarCode;

function adminApiHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

test('regular user cannot access users management endpoints', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/users', adminApiHeaders())
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson('/api/v1/users', [], adminApiHeaders())
        ->assertForbidden();
});

test('superadmin can list users', function () {
    User::factory()->count(3)->create();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->getJson('/api/v1/users', adminApiHeaders())
        ->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonPath('data.0.role', 'user');
});

test('superadmin can create a user with a library membership and auto barcode', function () {
    $admin = User::factory()->superAdmin()->create();
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();
    $library = Library::factory()->for($place)->create();

    $response = $this->actingAs($admin)->postJson('/api/v1/users', [
        'first_name' => 'Petar',
        'last_name' => 'Petrović',
        'username' => 'petar.petrovic',
        'email' => 'petar@example.com',
        'password' => 'secret123',
        'role' => 'user',
        'libraries' => [$library->id],
    ], adminApiHeaders());

    $response->assertCreated()
        ->assertJsonPath('data.email', 'petar@example.com')
        ->assertJsonPath('data.role', 'user')
        ->assertJsonPath('data.libraries.0.id', $library->id);

    $created = User::where('email', 'petar@example.com')->first();

    expect($created->role)->toBe(UserRole::User)
        ->and($created->bar_code)->toMatch('/^\d{13}$/')
        ->and(BarCode::validate($created->bar_code))->toBeTrue()
        ->and($created->libraries->pluck('id')->all())->toContain($library->id);
});

test('server ignores a client-provided barcode when creating a user', function () {
    $admin = User::factory()->superAdmin()->create();

    $response = $this->actingAs($admin)->postJson('/api/v1/users', [
        'first_name' => 'Milan',
        'last_name' => 'Milić',
        'email' => 'milan@example.com',
        'password' => 'secret123',
        'role' => 'user',
        'bar_code' => '1234567890128',
    ], adminApiHeaders());

    $response->assertCreated();
    $created = User::where('email', 'milan@example.com')->firstOrFail();

    expect($created->bar_code)->not->toBe('1234567890128')
        ->and(BarCode::validate($created->bar_code))->toBeTrue();
});

test('server allocates sequential EAN bases for users', function () {
    $admin = User::factory()->superAdmin()->create();

    $create = fn (string $email) => $this->actingAs($admin)->postJson('/api/v1/users', [
        'first_name' => 'Test',
        'last_name' => 'Korisnik',
        'email' => $email,
        'password' => 'secret123',
        'role' => 'user',
    ], adminApiHeaders())->assertCreated();

    $create('first@example.com');
    $create('second@example.com');

    $first = User::where('email', 'first@example.com')->value('bar_code');
    $second = User::where('email', 'second@example.com')->value('bar_code');

    expect((int) substr($second, 0, 12))->toBe(((int) substr($first, 0, 12)) + 1)
        ->and(BarCode::validate($first))->toBeTrue()
        ->and(BarCode::validate($second))->toBeTrue();
});

test('roles assignable reflects actor role', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)->getJson('/api/v1/roles/assignable', adminApiHeaders())
        ->assertOk()
        ->assertJsonCount(4, 'roles');

    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();

    $this->actingAs($libraryAdmin)->getJson('/api/v1/roles/assignable', adminApiHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'roles');
});

test('roles labels are localized via X-Locale header', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->getJson('/api/v1/roles', adminApiHeaders(['X-Locale' => 'sr-Cyrl']))
        ->assertOk()
        ->assertJsonPath('roles.0.value', 'superadmin')
        ->assertJsonPath('roles.0.label', 'Суперадминистратор');

    $this->actingAs($admin)->getJson('/api/v1/roles', adminApiHeaders(['X-Locale' => 'en']))
        ->assertOk()
        ->assertJsonPath('roles.0.label', 'Super Administrator');
});

test('non superadmin cannot access libraries endpoints', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/libraries', adminApiHeaders())
        ->assertForbidden();
});

test('superadmin can create and list a library', function () {
    $admin = User::factory()->superAdmin()->create();
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();

    $this->actingAs($admin)->postJson('/api/v1/libraries', [
        'name' => 'Gradska biblioteka',
        'address' => 'Trg slobode 1',
        'place_id' => $place->id,
        'work_time' => 'Pon - Pet, 08:00 - 20:00',
    ], adminApiHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Gradska biblioteka')
        ->assertJsonPath('data.place.id', $place->id)
        ->assertJsonPath('data.place.region.id', $region->id)
        ->assertJsonPath('data.inv_number_auto', true);

    $this->actingAs($admin)->getJson('/api/v1/libraries', adminApiHeaders())
        ->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonPath('data.0.name', 'Gradska biblioteka');
});

test('superadmin can toggle automatic inventory number on a library', function () {
    $admin = User::factory()->superAdmin()->create();
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();

    $create = $this->actingAs($admin)->postJson('/api/v1/libraries', [
        'name' => 'Narodna biblioteka',
        'address' => 'Knez Mihailova 1',
        'place_id' => $place->id,
        'inv_number_auto' => false,
    ], adminApiHeaders())
        ->assertCreated()
        ->assertJsonPath('data.inv_number_auto', false);

    $libraryId = $create->json('data.id');

    $this->actingAs($admin)->putJson("/api/v1/libraries/{$libraryId}", [
        'name' => 'Narodna biblioteka',
        'address' => 'Knez Mihailova 1',
        'place_id' => $place->id,
        'inv_number_auto' => true,
    ], adminApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.inv_number_auto', true);

    expect(Library::find($libraryId)?->inv_number_auto)->toBeTrue();
});

test('library inventory number flag must be a boolean', function () {
    $admin = User::factory()->superAdmin()->create();
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();

    $this->actingAs($admin)->postJson('/api/v1/libraries', [
        'name' => 'Gradska biblioteka',
        'address' => 'Trg slobode 1',
        'place_id' => $place->id,
        'inv_number_auto' => 'not-a-bool',
    ], adminApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('inv_number_auto');
});

test('me returns the authenticated user with role', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->getJson('/api/v1/auth/me', adminApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.role', 'superadmin')
        ->assertJsonPath('data.email', $admin->email);
});

test('me returns global permissions for a superadmin', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->getJson('/api/v1/auth/me', adminApiHeaders())
        ->assertOk()
        ->assertJsonPath('permissions.users.viewAny', true)
        ->assertJsonPath('permissions.users.create', true)
        ->assertJsonPath('permissions.libraries.viewAny', true)
        ->assertJsonPath('permissions.libraries.create', true);
});

test('me returns empty permissions for a regular user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/auth/me', adminApiHeaders())
        ->assertOk()
        ->assertJsonPath('permissions.users.viewAny', false)
        ->assertJsonPath('permissions.users.create', false)
        ->assertJsonPath('permissions.libraries.viewAny', false)
        ->assertJsonPath('permissions.libraries.create', false);
});

test('users list includes collection permissions and per-item can map', function () {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->count(2)->create();

    $this->actingAs($admin)->getJson('/api/v1/users', adminApiHeaders())
        ->assertOk()
        ->assertJsonPath('permissions.viewAny', true)
        ->assertJsonPath('permissions.create', true)
        ->assertJsonPath('data.0.can.view', true)
        ->assertJsonPath('data.0.can.update', true)
        ->assertJsonPath('data.0.can.delete', true);
});

test('library detail includes per-resource can map', function () {
    $admin = User::factory()->superAdmin()->create();
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();
    $library = Library::factory()->for($place)->create();

    $this->actingAs($admin)->getJson("/api/v1/libraries/{$library->id}", adminApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.can.view', true)
        ->assertJsonPath('data.can.update', true)
        ->assertJsonPath('data.can.delete', true);
});

test('superadmin can filter users by column', function () {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->create([
        'email' => 'ana@example.com',
        'username' => 'ana',
        'first_name' => 'Ana',
        'last_name' => 'Anić',
        'bar_code' => '1111111111111',
        'jmbg' => '0101990123456',
        'city' => 'Beograd',
    ]);
    User::factory()->create(['email' => 'marko@example.com', 'username' => 'marko', 'city' => 'Novi Sad']);

    $this->actingAs($admin)->getJson('/api/v1/users?email=ana@example.com', adminApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'ana@example.com');

    $this->actingAs($admin)->getJson('/api/v1/users?username=marko', adminApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.username', 'marko');

    $this->actingAs($admin)->getJson('/api/v1/users?city=beograd', adminApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.city', 'Beograd');
});

test('barcode search restores a dropped leading zero from a twelve digit scan', function () {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->create([
        'email' => 'barcode@example.com',
        'bar_code' => '0123456789012',
    ]);
    User::factory()->create(['email' => 'other@example.com', 'bar_code' => '0123456789020']);

    $this->actingAs($admin)
        ->getJson('/api/v1/users?bar_code=123456789012', adminApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);
});

test('superadmin can filter users by role', function () {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->count(2)->role(UserRole::Librarian)->create();
    User::factory()->count(3)->role(UserRole::User)->create();

    $this->actingAs($admin)->getJson('/api/v1/users?role=librarian', adminApiHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('superadmin can filter users by library membership', function () {
    $admin = User::factory()->superAdmin()->create();
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();
    $library = Library::factory()->for($place)->create();

    $member = User::factory()->create();
    $member->libraries()->sync([$library->id]);
    User::factory()->create();

    $this->actingAs($admin)->getJson("/api/v1/users?library_id={$library->id}", adminApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $member->id);
});

test('invalid role filter returns empty results without error', function () {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->count(3)->create();

    $this->actingAs($admin)->getJson('/api/v1/users?role=nonsense', adminApiHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('library admin library filter is ignored and scope relies on its libraries', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $libraryAdmin->libraries()->attach($libraryA->id);

    $member = User::factory()->create();
    $member->libraries()->attach($libraryA->id);
    $foreign = User::factory()->create();
    $foreign->libraries()->attach($libraryB->id);

    $response = $this->actingAs($libraryAdmin)
        ->getJson("/api/v1/users?library_id={$libraryB->id}", adminApiHeaders())
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($member->id)
        ->and($ids)->toContain($libraryAdmin->id)
        ->and($ids)->not->toContain($foreign->id);
});

test('superadmin library filter takes precedence over the active library', function () {
    $admin = User::factory()->superAdmin()->create();
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();

    $memberA = User::factory()->create();
    $memberA->libraries()->attach($libraryA->id);
    $memberB = User::factory()->create();
    $memberB->libraries()->attach($libraryB->id);

    app(ActiveLibraryService::class)->set($admin, $libraryA->id);

    $this->actingAs($admin)->getJson("/api/v1/users?library_id={$libraryB->id}", adminApiHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $memberB->id);
});
