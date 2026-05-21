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
        $post = Post::factory()->published()->create();
        $actor = User::factory()->verified()->create();

        return [
            'owner_user_id' => $post->user_id,
            'actor_user_id' => $actor->id,
            'action' => 'post_liked',
            'subject_type' => $post->getMorphClass(),
            'subject_id' => $post->id,
            'meta' => null,
        ];
    }
}
