<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostPhoto>
 */
class PostPhotoFactory extends Factory
{
    protected $model = PostPhoto::class;

    public function definition(): array
    {
        return [
            'post_id' => Post::factory()->published(),
            'path' => 'posts/seed/'.fake()->uuid().'.jpg',
            'sort_order' => 0,
        ];
    }
}
