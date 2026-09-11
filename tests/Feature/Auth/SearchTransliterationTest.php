<?php

use App\Models\Library;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use App\Models\User;

function translitHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function makeTranslitLibrary(): Library
{
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();

    return Library::factory()->for($place)->create();
}

test('user search finds a latin stored record using a cyrillic query', function () {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->create(['first_name' => 'Filipović', 'city' => 'Beograd']);
    User::factory()->create(['first_name' => 'Marko', 'city' => 'Novi Sad']);

    $this->actingAs($admin)
        ->getJson('/api/v1/users?first_name='.urlencode('Филиповић'), translitHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);

    $this->actingAs($admin)
        ->getJson('/api/v1/users?city='.urlencode('Београд'), translitHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);
});

test('user search finds a cyrillic stored record using a latin query', function () {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->create(['first_name' => 'Филиповић']);
    User::factory()->create(['first_name' => 'Марко']);

    $this->actingAs($admin)
        ->getJson('/api/v1/users?first_name='.urlencode('Filipović'), translitHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);
});

test('library search matches both scripts across name and place', function () {
    $admin = User::factory()->superAdmin()->create();
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create(['name' => 'Beograd']);
    $library = Library::factory()->for($place)->create(['name' => 'Narodna biblioteka']);
    Library::factory()->create(['name' => 'Druga biblioteka']);

    $this->actingAs($admin)
        ->getJson('/api/v1/libraries?search='.urlencode('Народна'), translitHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $library->id);

    $this->actingAs($admin)
        ->getJson('/api/v1/libraries?search='.urlencode('Београд'), translitHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $library->id);
});

test('tag search matches both scripts and respects the library filter', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeTranslitLibrary();
    $other = makeTranslitLibrary();
    $latin = Tag::factory()->for($library)->create(['name' => 'Član']);
    Tag::factory()->for($library)->create(['name' => 'Vip']);
    Tag::factory()->for($other)->create(['name' => 'Члан']);

    $this->actingAs($admin)
        ->getJson('/api/v1/tags?search='.urlencode('Члан'), translitHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($admin)
        ->getJson('/api/v1/tags?search='.urlencode('Члан')."&library_id={$library->id}", translitHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $latin->id);
});
