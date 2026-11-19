<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Route::middleware('auth')->post('/api/v1/testing/unique-violation', function () {
        DB::transaction(function (): void {
            DB::table('database_exception_test')->insert(['code' => 'same', 'value' => 1]);
            DB::table('database_exception_test')->insert(['code' => 'same', 'value' => 1]);
        });
    });

    Route::middleware('auth')->post('/api/v1/testing/check-violation', function () {
        DB::transaction(function (): void {
            DB::table('database_exception_test')->insert(['code' => 'invalid', 'value' => 0]);
        });
    });

    Schema::create('database_exception_test', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->unique();
        $table->integer('value');
        $table->timestamps();
    });

    DB::statement(
        'ALTER TABLE database_exception_test
         ADD CONSTRAINT database_exception_test_value_check CHECK (value > 0)'
    );
});

test('an unhandled unique violation returns a generic json 422', function () {
    $user = User::factory()->create();
    app()->setLocale('en');

    $this->actingAs($user)
        ->postJson('/api/v1/testing/unique-violation')
        ->assertStatus(422)
        ->assertJsonPath('message', 'A record with the same data already exists.');

    expect(DB::table('database_exception_test')->count())->toBe(0);
});

test('an unhandled check constraint violation returns a generic json 422', function () {
    $user = User::factory()->create();
    app()->setLocale('en');

    $this->actingAs($user)
        ->postJson('/api/v1/testing/check-violation')
        ->assertStatus(422)
        ->assertJsonPath('message', 'The data does not satisfy the database constraints.');

    expect(DB::table('database_exception_test')->count())->toBe(0);
});
