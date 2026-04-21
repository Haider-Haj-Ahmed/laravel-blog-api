<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Blog;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityFeedCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_unliking_a_post_removes_post_like_activity_from_history(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/posts/{$post->id}/toggle-like")->assertOk();

        $this->assertDatabaseHas('activities', [
            'owner_user_id' => $user->id,
            'action' => 'post_liked',
            'subject_type' => $post->getMorphClass(),
            'subject_id' => $post->id,
        ]);

        $this->postJson("/api/posts/{$post->id}/toggle-like")->assertOk();

        $this->assertDatabaseMissing('activities', [
            'owner_user_id' => $user->id,
            'action' => 'post_liked',
            'subject_type' => $post->getMorphClass(),
            'subject_id' => $post->id,
        ]);
    }

    public function test_unliking_a_blog_removes_blog_like_activity_from_history(): void
    {
        $user = User::factory()->create();
        $blog = Blog::create([
            'user_id' => $user->id,
            'title' => 'Test Blog',
            'subtitle' => 'Test subtitle',
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/blogs/{$blog->id}/toggle-like")->assertOk();

        $this->assertDatabaseHas('activities', [
            'owner_user_id' => $user->id,
            'action' => 'blog_liked',
            'subject_type' => $blog->getMorphClass(),
            'subject_id' => $blog->id,
        ]);

        $this->postJson("/api/blogs/{$blog->id}/toggle-like")->assertOk();

        $this->assertDatabaseMissing('activities', [
            'owner_user_id' => $user->id,
            'action' => 'blog_liked',
            'subject_type' => $blog->getMorphClass(),
            'subject_id' => $blog->id,
        ]);
    }

    public function test_deleting_a_post_comment_removes_comment_activity_from_history(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/comments', [
            'body' => 'My post comment',
            'post_id' => $post->id,
        ])->assertCreated();

        $commentId = $createResponse->json('data.id');
        $this->assertNotNull($commentId);

        $this->assertTrue(
            Activity::query()
                ->where('owner_user_id', $user->id)
                ->where('action', 'post_commented')
                ->where('subject_type', $post->getMorphClass())
                ->where('subject_id', $post->id)
                ->get()
                ->contains(fn (Activity $activity) => (int) ($activity->meta['comment_id'] ?? 0) === (int) $commentId)
        );

        $this->deleteJson("/api/comments/{$commentId}")->assertOk();

        $this->assertFalse(
            Activity::query()
                ->where('owner_user_id', $user->id)
                ->where('action', 'post_commented')
                ->get()
                ->contains(fn (Activity $activity) => (int) ($activity->meta['comment_id'] ?? 0) === (int) $commentId)
        );
    }

    public function test_deleting_a_blog_comment_removes_comment_activity_from_history(): void
    {
        $user = User::factory()->create();
        $blog = Blog::create([
            'user_id' => $user->id,
            'title' => 'History Blog',
            'subtitle' => 'History subtitle',
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/comments', [
            'body' => 'My blog comment',
            'blog_id' => $blog->id,
        ])->assertCreated();

        $commentId = $createResponse->json('data.id');
        $this->assertNotNull($commentId);

        $this->assertTrue(
            Activity::query()
                ->where('owner_user_id', $user->id)
                ->where('action', 'blog_commented')
                ->where('subject_type', $blog->getMorphClass())
                ->where('subject_id', $blog->id)
                ->get()
                ->contains(fn (Activity $activity) => (int) ($activity->meta['comment_id'] ?? 0) === (int) $commentId)
        );

        $this->deleteJson("/api/comments/{$commentId}")->assertOk();

        $this->assertFalse(
            Activity::query()
                ->where('owner_user_id', $user->id)
                ->where('action', 'blog_commented')
                ->get()
                ->contains(fn (Activity $activity) => (int) ($activity->meta['comment_id'] ?? 0) === (int) $commentId)
        );
    }
}
