<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $username = fake()->unique()->userName();

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'username' => Str::slug($username, '_'),
            'phone' => fake()->optional(0.3)->e164PhoneNumber(),
            'email_verified_at' => null,
            'phone_verified_at' => null,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => false,
        ];
    }

    public function withProfile(array $attributes = []): static
    {
        return $this->afterCreating(function (User $user) use ($attributes): void {
            Profile::query()->firstOrCreate(
                ['user_id' => $user->id],
                array_merge(ProfileFactory::new()->make()->toArray(), $attributes)
            );
        });
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => now(),
        ]);
    }

    public function phoneVerified(): static
    {
        return $this->state(fn () => [
            'phone_verified_at' => now(),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'is_admin' => true,
        ]);
    }
}
