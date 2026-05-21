<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'bio' => fake()->optional(0.7)->paragraph(),
            'website' => fake()->optional(0.3)->url(),
            'location' => fake()->optional(0.5)->city(),
            'social_links' => fake()->optional(0.4)->passthrough([
                'github' => 'https://github.com/'.fake()->userName(),
                'twitter' => 'https://twitter.com/'.fake()->userName(),
            ]),
            'ranking_points' => fake()->numberBetween(0, 1200),
            'last_seen_at' => fake()->optional(0.6)->dateTimeBetween('-1 week', 'now'),
            'settings' => [
                'email_notifications' => true,
                'push_notifications' => false,
            ],
        ];
    }

    public function forUser(User $user): static
    {
        return $this->for($user);
    }

    public function expert(): static
    {
        return $this->state(fn () => [
            'ranking_points' => fake()->numberBetween(5000, 8000),
        ]);
    }

    public function senior(): static
    {
        return $this->state(fn () => [
            'ranking_points' => fake()->numberBetween(1000, 4999),
        ]);
    }
}
