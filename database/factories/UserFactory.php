<?php

namespace Database\Factories;

use App\Enums\LibraryRole;
use App\Models\Library;
use App\Models\User;
use App\Services\GlobalSettings;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
        ];
    }

    /**
     * Globalna superadmin privilegija se cuva u settings tabeli.
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            app(GlobalSettings::class)->addSuperAdmin((int) $user->id);
        });
    }

    /**
     * Dodaje aktivno clanstvo sa konkretnom ulogom u biblioteci.
     */
    public function libraryRole(Library $library, LibraryRole $role = LibraryRole::User): static
    {
        return $this->afterCreating(function (User $user) use ($library, $role): void {
            $user->libraries()->attach($library->id, ['role' => $role->value]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
