<?php

use App\Models\News;
use App\Models\User;

function newsCrudHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function newsPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Nova novost o eBiblioteci',
        'body' => '<p>Sadržaj novosti.</p>',
        'date' => '2026-09-09',
        'image' => null,
    ], $overrides);
}

test('superadmin can list news paginated with meta', function () {
    $admin = User::factory()->superAdmin()->create();
    News::factory()->count(30)->create();

    $this->actingAs($admin)->getJson('/api/v1/news?per_page=10', newsCrudHeaders())
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
});

test('superadmin can create update and delete a news article', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->postJson('/api/v1/news', newsPayload(), newsCrudHeaders())
        ->assertCreated()
        ->assertJsonPath('data.title', 'Nova novost o eBiblioteci')
        ->assertJsonPath('data.slug', 'nova-novost-o-ebiblioteci');

    $news = News::where('slug', 'nova-novost-o-ebiblioteci')->firstOrFail();

    $this->actingAs($admin)->putJson("/api/v1/news/{$news->id}", newsPayload(['title' => 'Izmenjen naslov']), newsCrudHeaders())
        ->assertOk()
        ->assertJsonPath('data.title', 'Izmenjen naslov');

    $this->actingAs($admin)->deleteJson("/api/v1/news/{$news->id}", [], newsCrudHeaders())
        ->assertNoContent();

    expect(News::find($news->id))->toBeNull();
});

test('news requires title body and date', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->postJson('/api/v1/news', ['title' => '', 'body' => '', 'date' => ''], newsCrudHeaders())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'body', 'date']);
});

test('regular user and library admin cannot access news', function () {
    $user = User::factory()->create();
    $libraryAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($user)->getJson('/api/v1/news', newsCrudHeaders())
        ->assertForbidden();

    $libraryAdmin->update(['role' => App\Enums\UserRole::LibraryAdmin]);

    $this->actingAs($libraryAdmin)->getJson('/api/v1/news', newsCrudHeaders())
        ->assertForbidden();
});
