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
        $blocker = User::factory()->verified()->create();
        $blocked = User::factory()->verified()->create();

        return [
            'user_id' => $blocker->id,
            'blocked_user_id' => $blocked->id,
        ];
    }
}
