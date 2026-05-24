<?php

namespace Database\Factories;

use App\Models\Blog;
use App\Models\BlogLike;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogLike>
 */
class BlogLikeFactory extends Factory
{
    protected $model = BlogLike::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->verified(),
            'blog_id' => Blog::factory()->published(),
        ];
    }
}
