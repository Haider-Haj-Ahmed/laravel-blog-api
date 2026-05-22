<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBlock>
 */
class UserBlockFactory extends Factory
{
    protected $model = UserBlock::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->verified(),
            'blocked_user_id' => User::factory()->verified(),
        ];
    }
}
