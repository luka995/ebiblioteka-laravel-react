<?php

use App\Enums\UserRole;
use App\Models\Library;
use App\Models\User;
use App\Services\ActiveLibraryService;

function bulkBarcodePrintHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

test('superadmin can bulk print one label page per user', function () {
    $admin = User::factory()->superAdmin()->create();
    $first = User::factory()->create(['bar_code' => '4006381333931']);
    $second = User::factory()->create(['bar_code' => '0000000001236']);

    $response = $this->actingAs($admin)->postJson('/api/v1/users/bulk/barcode/print', [
        'user_ids' => [$first->id, $second->id],
        'format' => 'label',
    ], bulkBarcodePrintHeaders());

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="barkodovi.pdf"');

    expect($response->getContent())->toStartWith('%PDF')
        ->and(preg_match_all('/MediaBox/', $response->getContent()))->toBe(2);
});

test('superadmin can bulk print selected users on a single a4 sheet', function () {
    $admin = User::factory()->superAdmin()->create();
    $first = User::factory()->create(['bar_code' => '4006381333931']);
    $second = User::factory()->create(['bar_code' => '0000000001236']);

    $response = $this->actingAs($admin)->postJson('/api/v1/users/bulk/barcode/print', [
        'user_ids' => [$first->id, $second->id],
        'format' => 'a4',
    ], bulkBarcodePrintHeaders());

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect(preg_match_all('/MediaBox/', $response->getContent()))->toBe(1);
});

test('bulk printing does not regenerate barcodes', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => '4006381333931']);

    $this->actingAs($admin)->postJson('/api/v1/users/bulk/barcode/print', [
        'user_ids' => [$user->id],
        'format' => 'label',
    ], bulkBarcodePrintHeaders())->assertOk();

    expect($user->fresh()->bar_code)->toBe('4006381333931');
});

test('bulk print requires at least one user id', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->postJson('/api/v1/users/bulk/barcode/print', [
        'user_ids' => [],
        'format' => 'label',
    ], bulkBarcodePrintHeaders())->assertStatus(422)->assertJsonValidationErrors('user_ids');

    $this->actingAs($admin)->postJson('/api/v1/users/bulk/barcode/print', [
        'format' => 'label',
    ], bulkBarcodePrintHeaders())->assertStatus(422)->assertJsonValidationErrors('user_ids');
});

test('bulk print rejects an unknown format', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => '4006381333931']);

    $this->actingAs($admin)->postJson('/api/v1/users/bulk/barcode/print', [
        'user_ids' => [$user->id],
        'format' => 'bogus',
    ], bulkBarcodePrintHeaders())->assertStatus(422)->assertJsonValidationErrors('format');
});

test('bulk print rejects users without a valid barcode', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create(['bar_code' => null]);

    $this->actingAs($admin)->postJson('/api/v1/users/bulk/barcode/print', [
        'user_ids' => [$user->id],
        'format' => 'label',
    ], bulkBarcodePrintHeaders())->assertStatus(422);
});

test('librarian cannot bulk print barcodes', function () {
    $librarian = User::factory()->role(UserRole::Librarian)->create();
    $target = User::factory()->create(['bar_code' => '4006381333931']);

    $this->actingAs($librarian)->postJson('/api/v1/users/bulk/barcode/print', [
        'user_ids' => [$target->id],
        'format' => 'label',
    ], bulkBarcodePrintHeaders())->assertForbidden();
});

test('library admin can bulk print active library members', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $library = Library::factory()->create();
    $libraryAdmin->libraries()->attach($library->id);
    $member = User::factory()->create(['bar_code' => '4006381333931']);
    $member->libraries()->attach($library->id);

    app(ActiveLibraryService::class)->set($libraryAdmin, $library->id);

    $this->actingAs($libraryAdmin)->postJson('/api/v1/users/bulk/barcode/print', [
        'user_ids' => [$member->id],
        'format' => 'label',
    ], bulkBarcodePrintHeaders())->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('library admin without an active library cannot bulk print', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $first = Library::factory()->create();
    $second = Library::factory()->create();
    $libraryAdmin->libraries()->attach([$first->id, $second->id]);
    $member = User::factory()->create(['bar_code' => '4006381333931']);
    $member->libraries()->attach($first->id);

    $this->actingAs($libraryAdmin)->postJson('/api/v1/users/bulk/barcode/print', [
        'user_ids' => [$member->id],
        'format' => 'label',
    ], bulkBarcodePrintHeaders())->assertStatus(422);
});

test('library admin cannot bulk print users outside the active library', function () {
    $libraryAdmin = User::factory()->role(UserRole::LibraryAdmin)->create();
    $library = Library::factory()->create();
    $libraryAdmin->libraries()->attach($library->id);
    $outsider = User::factory()->create(['bar_code' => '4006381333931']);

    app(ActiveLibraryService::class)->set($libraryAdmin, $library->id);

    $this->actingAs($libraryAdmin)->postJson('/api/v1/users/bulk/barcode/print', [
        'user_ids' => [$outsider->id],
        'format' => 'label',
    ], bulkBarcodePrintHeaders())->assertStatus(422);
});
