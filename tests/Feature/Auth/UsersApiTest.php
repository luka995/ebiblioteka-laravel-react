<?php

use App\Enums\UserRole;
use App\Models\Library;
use App\Models\Place;
use App\Models\Region;
use App\Models\User;

function adminApiHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

test('non superadmin cannot access users management endpoints', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/users', adminApiHeaders())
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson('/api/v1/users', [], adminApiHeaders())
        ->assertForbidden();

    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();

    $this->actingAs($libraryAdmin)
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
        ->and($created->libraries->pluck('id')->all())->toContain($library->id);
});

test('next barcode endpoint returns a unique 13 digit code', function () {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->create(['bar_code' => '1000000000001']);

    $response = $this->actingAs($admin)->getJson('/api/v1/users/barcode/next', adminApiHeaders());

    $response->assertOk();
    $code = $response->json('bar_code');

    expect($code)->toMatch('/^\d{13}$/')
        ->and(User::where('bar_code', $code)->exists())->toBeFalse();
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
        ->assertJsonPath('data.place.region.id', $region->id);

    $this->actingAs($admin)->getJson('/api/v1/libraries', adminApiHeaders())
        ->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonPath('data.0.name', 'Gradska biblioteka');
});

test('me returns the authenticated user with role', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->getJson('/api/v1/auth/me', adminApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.role', 'superadmin')
        ->assertJsonPath('data.email', $admin->email);
});
