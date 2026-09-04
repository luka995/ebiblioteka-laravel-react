<?php

use App\Enums\UserRole;
use App\Models\Library;
use App\Models\Place;
use App\Models\Region;
use App\Models\User;

function regionPlaceHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

test('superadmin can list regions paginated with meta', function () {
    $admin = User::factory()->superAdmin()->create();
    foreach (range(1, 30) as $i) {
        Region::create(['name' => "Regija {$i}"]);
    }

    $this->actingAs($admin)->getJson('/api/v1/regions?per_page=10', regionPlaceHeaders())
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
});

test('superadmin can create and update a region', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->postJson('/api/v1/regions', ['name' => 'Vojvodina'], regionPlaceHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Vojvodina');

    $region = Region::where('name', 'Vojvodina')->firstOrFail();

    $this->actingAs($admin)->putJson("/api/v1/regions/{$region->id}", ['name' => 'Šumadija'], regionPlaceHeaders())
        ->assertOk()
        ->assertJsonPath('data.name', 'Šumadija');
});

test('superadmin cannot delete a region that has places', function () {
    $admin = User::factory()->superAdmin()->create();
    $region = Region::factory()->create();
    Place::factory()->for($region)->create();

    $this->actingAs($admin)->deleteJson("/api/v1/regions/{$region->id}", [], regionPlaceHeaders())
        ->assertStatus(422);

    expect(Region::find($region->id))->not->toBeNull();
});

test('superadmin can delete an empty region', function () {
    $admin = User::factory()->superAdmin()->create();
    $region = Region::factory()->create();

    $this->actingAs($admin)->deleteJson("/api/v1/regions/{$region->id}", [], regionPlaceHeaders())
        ->assertNoContent();

    expect(Region::find($region->id))->toBeNull();
});

test('superadmin can create and update a place', function () {
    $admin = User::factory()->superAdmin()->create();
    $region = Region::factory()->create();

    $this->actingAs($admin)->postJson('/api/v1/places', ['name' => 'Novi Sad', 'region_id' => $region->id], regionPlaceHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Novi Sad')
        ->assertJsonPath('data.region_id', $region->id);

    $place = Place::where('name', 'Novi Sad')->firstOrFail();

    $this->actingAs($admin)->putJson("/api/v1/places/{$place->id}", ['name' => 'Zrenjanin', 'region_id' => $region->id], regionPlaceHeaders())
        ->assertOk()
        ->assertJsonPath('data.name', 'Zrenjanin');
});

test('superadmin cannot delete a place that has libraries', function () {
    $admin = User::factory()->superAdmin()->create();
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();
    Library::factory()->for($place)->create();

    $this->actingAs($admin)->deleteJson("/api/v1/places/{$place->id}", [], regionPlaceHeaders())
        ->assertStatus(422);

    expect(Place::find($place->id))->not->toBeNull();
});

test('non superadmin cannot access regions or places', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();

    $this->actingAs($libraryAdmin)->getJson('/api/v1/regions', regionPlaceHeaders())
        ->assertForbidden();

    $this->actingAs($libraryAdmin)->getJson('/api/v1/places', regionPlaceHeaders())
        ->assertForbidden();
});
