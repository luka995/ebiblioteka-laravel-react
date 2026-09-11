<?php

use App\Enums\UserRole;
use App\Models\Library;
use App\Models\User;
use App\Services\ActiveLibraryService;
use App\Support\BarCode;

function bulkBarcodeHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

test('superadmin can bulk regenerate barcodes', function () {
    $admin = User::factory()->superAdmin()->create();
    $first = User::factory()->create(['bar_code' => '0000000001236']);
    $second = User::factory()->create(['bar_code' => '0000000004568']);

    $this->actingAs($admin)->postJson('/api/v1/users/bulk/barcode', [
        'user_ids' => [$first->id, $second->id],
    ], bulkBarcodeHeaders())->assertNoContent();

    $codes = [$first->fresh()->bar_code, $second->fresh()->bar_code];

    expect($codes)->not->toContain('0000000001236')
        ->and($codes)->not->toContain('0000000004568')
        ->and($codes[0])->not->toBe($codes[1]);

    foreach ($codes as $code) {
        expect(BarCode::validate($code))->toBeTrue();
    }
});

test('library admin can bulk regenerate barcodes for active library members', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $library = Library::factory()->create();
    $libraryAdmin->libraries()->attach($library->id);
    $member = User::factory()->create(['bar_code' => '0000000001236']);
    $member->libraries()->attach($library->id);

    app(ActiveLibraryService::class)->set($libraryAdmin, $library->id);

    $this->actingAs($libraryAdmin)->postJson('/api/v1/users/bulk/barcode', [
        'user_ids' => [$member->id],
    ], bulkBarcodeHeaders())->assertNoContent();

    $fresh = $member->fresh()->bar_code;

    expect($fresh)->not->toBe('0000000001236')
        ->and(BarCode::validate($fresh))->toBeTrue();
});

test('library admin without an active library cannot bulk regenerate barcodes', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $first = Library::factory()->create();
    $second = Library::factory()->create();
    $libraryAdmin->libraries()->attach([$first->id, $second->id]);
    $member = User::factory()->create(['bar_code' => '0000000001236']);
    $member->libraries()->attach($first->id);

    $this->actingAs($libraryAdmin)->postJson('/api/v1/users/bulk/barcode', [
        'user_ids' => [$member->id],
    ], bulkBarcodeHeaders())->assertStatus(422);

    expect($member->fresh()->bar_code)->toBe('0000000001236');
});

test('library admin cannot bulk regenerate barcodes for users outside the active library', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $library = Library::factory()->create();
    $libraryAdmin->libraries()->attach($library->id);
    $outsider = User::factory()->create(['bar_code' => '0000000001236']);

    app(ActiveLibraryService::class)->set($libraryAdmin, $library->id);

    $this->actingAs($libraryAdmin)->postJson('/api/v1/users/bulk/barcode', [
        'user_ids' => [$outsider->id],
    ], bulkBarcodeHeaders())->assertStatus(422);

    expect($outsider->fresh()->bar_code)->toBe('0000000001236');
});

test('librarian cannot bulk regenerate barcodes', function () {
    $librarian = User::factory()->role(UserRole::Librarian)->create();
    $target = User::factory()->create(['bar_code' => '0000000001236']);

    $this->actingAs($librarian)->postJson('/api/v1/users/bulk/barcode', [
        'user_ids' => [$target->id],
    ], bulkBarcodeHeaders())->assertForbidden();

    expect($target->fresh()->bar_code)->toBe('0000000001236');
});

test('bulk barcode regeneration requires at least one user id', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->postJson('/api/v1/users/bulk/barcode', [
        'user_ids' => [],
    ], bulkBarcodeHeaders())->assertStatus(422);

    $this->actingAs($admin)->postJson('/api/v1/users/bulk/barcode', [], bulkBarcodeHeaders())
        ->assertStatus(422);
});
