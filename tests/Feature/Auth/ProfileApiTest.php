<?php

use App\Enums\UserRole;
use App\Models\Library;
use App\Models\Place;
use App\Models\Region;
use App\Models\User;

function profileApiHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

test('authenticated user can fetch their own profile with libraries', function () {
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();
    $library = Library::factory()->for($place)->create();

    $user = User::factory()->role(UserRole::Librarian)->create();
    $user->libraries()->sync([$library->id]);

    $this->actingAs($user)->getJson('/api/v1/profile', profileApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.role', 'librarian')
        ->assertJsonPath('data.libraries.0.id', $library->id);
});

test('unauthenticated request to profile returns 401', function () {
    $this->getJson('/api/v1/profile', profileApiHeaders())
        ->assertUnauthorized();

    $this->putJson('/api/v1/profile', [], profileApiHeaders())
        ->assertUnauthorized();

    $this->putJson('/api/v1/profile/password', [], profileApiHeaders())
        ->assertUnauthorized();
});

test('authenticated user can update their own profile', function () {
    $user = User::factory()->role(UserRole::User)->create([
        'email' => 'before@example.com',
    ]);

    $this->actingAs($user)->putJson('/api/v1/profile', [
        'first_name' => 'Mara',
        'last_name' => 'Marić',
        'username' => 'mara.maric',
        'email' => 'after@example.com',
        'jmbg' => '0101990711234',
        'address' => 'Kralja Petra 5',
        'city' => 'Beograd',
        'post_code' => '11000',
    ], profileApiHeaders())
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Mara')
        ->assertJsonPath('data.email', 'after@example.com')
        ->assertJsonPath('data.name', 'Mara Marić');

    $fresh = $user->fresh();

    expect($fresh->email)->toBe('after@example.com')
        ->and($fresh->username)->toBe('mara.maric')
        ->and($fresh->city)->toBe('Beograd');
});

test('role, libraries and bar_code cannot be changed via profile update', function () {
    $region = Region::factory()->create();
    $place = Place::factory()->for($region)->create();
    $library = Library::factory()->for($place)->create();

    $user = User::factory()->role(UserRole::User)->create([
        'bar_code' => '1000000000123',
    ]);

    $this->actingAs($user)->putJson('/api/v1/profile', [
        'first_name' => 'Mara',
        'last_name' => 'Marić',
        'email' => $user->email,
        'role' => 'superadmin',
        'bar_code' => '9999999999999',
        'libraries' => [$library->id],
    ], profileApiHeaders())
        ->assertOk();

    $fresh = $user->fresh();

    expect($fresh->role)->toBe(UserRole::User)
        ->and($fresh->bar_code)->toBe('1000000000123')
        ->and($fresh->libraries->count())->toBe(0);
});

test('profile update ignores the password field', function () {
    $user = User::factory()->role(UserRole::User)->create();
    $originalPassword = $user->password;

    $this->actingAs($user)->putJson('/api/v1/profile', [
        'first_name' => 'Mara',
        'last_name' => 'Marić',
        'email' => $user->email,
        'password' => 'newsecret123',
    ], profileApiHeaders())
        ->assertOk();

    expect($user->fresh()->password)->toBe($originalPassword);
});

test('profile password update requires a valid current password', function () {
    $user = User::factory()->role(UserRole::User)->create();

    $this->actingAs($user)->putJson('/api/v1/profile/password', [
        'current_password' => 'wrong-password',
        'password' => 'newsecret123',
        'password_confirmation' => 'newsecret123',
    ], profileApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');
});

test('profile password update changes the password with a valid current password', function () {
    $user = User::factory()->role(UserRole::User)->create();
    $originalPassword = $user->password;

    $this->actingAs($user)->putJson('/api/v1/profile/password', [
        'current_password' => 'password',
        'password' => 'newsecret123',
        'password_confirmation' => 'newsecret123',
    ], profileApiHeaders())
        ->assertNoContent();

    expect($user->fresh()->password)->not->toBe($originalPassword);
});

test('profile email must remain unique ignoring self', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'mine@example.com']);

    $this->actingAs($user)->putJson('/api/v1/profile', [
        'first_name' => 'Mara',
        'last_name' => 'Marić',
        'email' => 'taken@example.com',
    ], profileApiHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});
