<?php

use App\Models\User;

function barcodeApiHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'image/svg+xml',
    ], $extra);
}

test('authorized user can retrieve a users barcode SVG', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => '4006381333931']);

    $this->actingAs($admin)
        ->get("/api/v1/users/{$user->id}/barcode", barcodeApiHeaders())
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
        ->assertSee('<svg', false)
        ->assertSee('EAN-13 barcode 4006381333931', false);
});

test('user detail response includes the barcode SVG without a second request', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => '4006381333931']);

    $this->actingAs($admin)
        ->getJson("/api/v1/users/{$user->id}", barcodeApiHeaders(['Accept' => 'application/json']))
        ->assertOk()
        ->assertJsonPath('data.bar_code', '4006381333931')
        ->assertJsonPath('data.bar_code_svg', fn ($svg) => is_string($svg) && str_contains($svg, '<svg'));
});

test('users collection does not include barcode SVG payloads', function () {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->create(['bar_code' => '4006381333931']);

    $this->actingAs($admin)
        ->getJson('/api/v1/users', barcodeApiHeaders(['Accept' => 'application/json']))
        ->assertOk()
        ->assertJsonMissingPath('data.0.bar_code_svg');
});

test('user without a valid barcode gets not found from barcode endpoint', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => null]);

    $this->actingAs($admin)
        ->get("/api/v1/users/{$user->id}/barcode", barcodeApiHeaders())
        ->assertNotFound();
});

test('user without permission cannot retrieve another users barcode', function () {
    $regularUser = User::factory()->create();
    $target = User::factory()->create(['bar_code' => '4006381333931']);

    $this->actingAs($regularUser)
        ->get("/api/v1/users/{$target->id}/barcode", barcodeApiHeaders())
        ->assertForbidden();
});
