<?php

use App\Models\User;
use App\Services\Security\TurnstileService;

function apiStatefulHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ];
}

function allowTurnstile(): void
{
    test()->mock(TurnstileService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(true);
    });
}

test('users can authenticate using the login screen', function () {
    allowTurnstile();
    $user = User::factory()->create();

    $response = $this->post('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'turnstile_token' => 'valid-turnstile-token',
    ], apiStatefulHeaders());

    $this->assertAuthenticated();
    $response->assertNoContent();
});

test('users can not authenticate with invalid password', function () {
    allowTurnstile();
    $user = User::factory()->create();

    $this->post('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'turnstile_token' => 'valid-turnstile-token',
    ], apiStatefulHeaders());

    $this->assertGuest();
});

test('users can not authenticate without a turnstile token', function () {
    $user = User::factory()->create();

    $response = $this->post('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ], apiStatefulHeaders());

    $this->assertGuest();
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('turnstile_token');
});

test('users can not authenticate with an invalid turnstile token', function () {
    $this->mock(TurnstileService::class, function ($mock) {
        $mock->shouldReceive('verify')->once()->andReturn(false);
    });
    $user = User::factory()->create();

    $response = $this->post('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'turnstile_token' => 'invalid-turnstile-token',
    ], apiStatefulHeaders());

    $this->assertGuest();
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('turnstile_token');
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/api/v1/auth/logout', [], apiStatefulHeaders());

    $this->assertGuest();
    $response->assertNoContent();
});
