<?php

namespace Database\Factories;

use App\Models\Blog;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->verified(),
            'post_id' => Post::factory()->published(),
            'blog_id' => null,
            'parent_id' => null,
            'body' => fake()->paragraph(),
            'code' => null,
            'code_language' => null,
            'is_highlighted' => false,
            'is_modified' => false,
        ];
    }

    public function forPost(Post $post): static
    {
        return $this->state(fn () => [
            'post_id' => $post->id,
            'blog_id' => null,
        ]);
    }

    public function forBlog(Blog $blog): static
    {
        return $this->state(fn () => [
            'post_id' => null,
            'blog_id' => $blog->id,
        ]);
    }

    public function replyTo(Comment $parent): static
    {
        return $this->state(fn () => [
            'parent_id' => $parent->id,
            'post_id' => $parent->post_id,
            'blog_id' => $parent->blog_id,
        ]);
    }

    public function withCode(string $language = 'javascript'): static
    {
        return $this->state(fn () => [
            'code' => "console.log('seed comment');",
            'code_language' => $language,
        ]);
    }

    public function highlighted(): static
    {
        return $this->state(fn () => ['is_highlighted' => true]);
    }
}
