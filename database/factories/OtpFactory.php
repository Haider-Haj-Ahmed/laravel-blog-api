<?php

namespace Database\Factories;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Otp>
 */
class OtpFactory extends Factory
{
    protected $model = Otp::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code' => Hash::make('123456'),
            'channel' => 'email',
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
