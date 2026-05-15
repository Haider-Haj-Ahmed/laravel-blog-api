<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostPublishedCounterOnDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_published_post_decrements_user_counter_once(): void
    {
        $user = User::factory()->create(['published_posts_count' => 1]);
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'is_published' => true,
        ]);

        $post->delete();

        $user->refresh();
        $this->assertSame(0, $user->published_posts_count);
    }

    public function test_api_destroy_leaves_counter_consistent(): void
    {
        $user = User::factory()->create(['published_posts_count' => 1]);
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/posts/{$post->id}")
            ->assertOk();

        $user->refresh();
        $this->assertSame(0, $user->published_posts_count);
    }
}
