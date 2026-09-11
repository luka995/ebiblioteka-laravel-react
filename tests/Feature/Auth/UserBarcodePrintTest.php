<?php

use App\Models\User;

function barcodePrintHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/pdf',
    ], $extra);
}

test('authorized user can download the label barcode pdf', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => '4006381333931']);

    $response = $this->actingAs($admin)
        ->get("/api/v1/users/{$user->id}/barcode/print?format=label", barcodePrintHeaders());

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="barkod-4006381333931.pdf"');

    expect($response->getContent())->toStartWith('%PDF');
});

test('authorized user can download the a4 barcode pdf', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => '4006381333931']);

    $response = $this->actingAs($admin)
        ->get("/api/v1/users/{$user->id}/barcode/print?format=a4", barcodePrintHeaders());

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');
});

test('printing a barcode does not regenerate it', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => '4006381333931']);

    $this->actingAs($admin)
        ->get("/api/v1/users/{$user->id}/barcode/print?format=label", barcodePrintHeaders())
        ->assertOk();

    expect($user->fresh()->bar_code)->toBe('4006381333931');
});

test('an unknown print format is rejected', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => '4006381333931']);

    $this->actingAs($admin)
        ->getJson("/api/v1/users/{$user->id}/barcode/print?format=bogus", ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('format');

    $this->actingAs($admin)
        ->getJson("/api/v1/users/{$user->id}/barcode/print", ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('format');
});

test('user without permission cannot print another users barcode', function () {
    $regularUser = User::factory()->create();
    $target = User::factory()->create(['bar_code' => '4006381333931']);

    $this->actingAs($regularUser)
        ->get("/api/v1/users/{$target->id}/barcode/print?format=label", barcodePrintHeaders())
        ->assertForbidden();
});

test('user without a valid barcode cannot be printed', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => null]);

    $this->actingAs($admin)
        ->get("/api/v1/users/{$user->id}/barcode/print?format=label", barcodePrintHeaders())
        ->assertNotFound();
});
