<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_deleting_user_cleans_up_post_photos_before_cascade(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'is_published' => true,
        ]);

        Storage::disk('public')->put('post_photos/user-delete-photo.jpg', 'content');

        PostPhoto::create([
            'post_id' => $post->id,
            'path' => 'post_photos/user-delete-photo.jpg',
            'sort_order' => 0,
        ]);

        $this->assertTrue(Storage::disk('public')->exists('post_photos/user-delete-photo.jpg'));

        $user->delete();

        $this->assertFalse(Storage::disk('public')->exists('post_photos/user-delete-photo.jpg'));
    }
}
