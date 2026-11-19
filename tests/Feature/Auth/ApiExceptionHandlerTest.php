<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware('auth')->get('/api/v1/testing/unhandled', function () {
        throw new RuntimeException('boom');
    });
});

test('an unhandled api exception returns a generic json 500', function () {
    $user = User::factory()->create();
    app()->setLocale('en');

    $this->actingAs($user)
        ->getJson('/api/v1/testing/unhandled')
        ->assertStatus(500)
        ->assertJsonPath('message', 'An unexpected server error occurred. Please try again later.');
});
