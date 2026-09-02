<?php

use App\Models\User;

function apiStatefulHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ];
}

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ], apiStatefulHeaders());

    $this->assertAuthenticated();
    $response->assertNoContent();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ], apiStatefulHeaders());

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/api/v1/auth/logout', [], apiStatefulHeaders());

    $this->assertGuest();
    $response->assertNoContent();
});
