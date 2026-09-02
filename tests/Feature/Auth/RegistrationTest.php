<?php

test('new users can register', function () {
    $response = $this->post('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ]);

    $this->assertAuthenticated();
    $response->assertNoContent();
});
