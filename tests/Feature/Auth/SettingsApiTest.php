<?php

use App\Models\SettingLog;
use App\Models\User;
use App\Services\GlobalSettings;
use App\Services\UserAccessContext;

function settingsHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

test('superadmin can list and update the global superadmin list', function () {
    $first = User::factory()->superAdmin()->create();
    $second = User::factory()->create();

    $this->actingAs($first)
        ->getJson('/api/v1/settings', settingsHeaders())
        ->assertOk()
        ->assertJsonPath('data.super_admin_user_ids', [$first->id]);

    $this->actingAs($first)
        ->putJson('/api/v1/settings', [
            'super_admin_user_ids' => [$first->id, $second->id],
        ], settingsHeaders())
        ->assertOk()
        ->assertJsonPath('data.super_admin_user_ids', [$first->id, $second->id]);

    expect($second->isSuperAdmin())->toBeTrue()
        ->and(app(GlobalSettings::class)->superAdminIds())->toContain($second->id)
        ->and(SettingLog::query()->where('key', GlobalSettings::SUPER_ADMIN_USER_IDS)->count())->toBeGreaterThan(0);
});

test('regular user cannot access global settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/settings', settingsHeaders())
        ->assertForbidden();

    $this->actingAs($user)
        ->putJson('/api/v1/settings', ['super_admin_user_ids' => [$user->id]], settingsHeaders())
        ->assertForbidden();
});

test('the superadmin list must contain at least one valid user', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->putJson('/api/v1/settings', ['super_admin_user_ids' => []], settingsHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('super_admin_user_ids');

    $this->actingAs($admin)
        ->putJson('/api/v1/settings', ['super_admin_user_ids' => [999999]], settingsHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('super_admin_user_ids.0');
});

test('ensure super admin command adds the account to settings', function () {
    $this->artisan('app:ensure-super-admin', ['--email' => 'admin@ebiblioteka.rs'])
        ->assertSuccessful();

    $user = User::where('email', 'admin@ebiblioteka.rs')->firstOrFail();

    expect(app(GlobalSettings::class)->superAdminIds())->toContain($user->id)
        ->and(app(UserAccessContext::class)->isSuperAdmin($user))->toBeTrue();
});


test('settings index removes physically deleted superadmin ids', function () {
    $admin = User::factory()->superAdmin()->create();
    $settings = app(GlobalSettings::class);
    $settings->set(GlobalSettings::SUPER_ADMIN_USER_IDS, [$admin->id, 999999]);

    $this->actingAs($admin)
        ->getJson('/api/v1/settings', settingsHeaders())
        ->assertOk()
        ->assertJsonPath('data.super_admin_user_ids', [$admin->id]);

    expect($settings->superAdminIds())->toBe([$admin->id]);
});

test('force deleting a superadmin removes the id from settings', function () {
    $admin = User::factory()->create();
    $other = User::factory()->create();
    $settings = app(GlobalSettings::class);
    $settings->set(GlobalSettings::SUPER_ADMIN_USER_IDS, [$admin->id, $other->id]);

    $other->forceDelete();

    expect($settings->superAdminIds())->toContain($admin->id)
        ->and($settings->superAdminIds())->not->toContain($other->id);
});
