<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        $owner = User::factory()->verified();

        return [
            'owner_user_id' => $owner,
            'actor_user_id' => User::factory()->verified(),
            'action' => 'post_liked',
            'subject_type' => (new Post())->getMorphClass(),
            'subject_id' => Post::factory()->published()->state([
                'user_id' => $owner,
            ]),
            'meta' => null,
        ];
    }
}
