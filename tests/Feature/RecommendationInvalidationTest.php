<?php

namespace Tests\Feature;

use App\Events\CommentLiked;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Tag;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\RecommendationCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class RecommendationInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_like_bumps_recommendation_version(): void
    {
        $viewer = User::factory()->create();
        $post = $this->makePublishedPost();

        $this->mock(ActivityService::class, function (MockInterface $mock) use ($viewer, $post): void {
            $mock->shouldReceive('logUserInteraction')->once()->withArgs(function ($actor, $subject, $action) use ($viewer, $post): bool {
                return $actor->id === $viewer->id
                    && $subject->id === $post->id
                    && $action === 'post_liked';
            });
        });

        $this->mock(RecommendationCacheService::class, function (MockInterface $mock) use ($viewer): void {
            $mock->shouldReceive('bumpUserVersion')->once()->with($viewer->id)->andReturn(2);
        });

        Sanctum::actingAs($viewer);

        $this->postJson('/api/posts/' . $post->id . '/toggle-like')
            ->assertOk();
    }

    public function test_saving_a_post_bumps_recommendation_version(): void
    {
        $viewer = User::factory()->create();
        $post = $this->makePublishedPost();

        $this->mock(RecommendationCacheService::class, function (MockInterface $mock) use ($viewer): void {
            $mock->shouldReceive('bumpUserVersion')->once()->with($viewer->id)->andReturn(2);
        });

        Sanctum::actingAs($viewer);

        $this->postJson('/api/saves', ['type' => 'post', 'id' => $post->id])
            ->assertOk();
    }

    public function test_recording_a_post_view_bumps_recommendation_version(): void
    {
        $viewer = User::factory()->create();
        $post = $this->makePublishedPost();

        $this->mock(RecommendationCacheService::class, function (MockInterface $mock) use ($viewer): void {
            $mock->shouldReceive('bumpUserVersion')->once()->with($viewer->id)->andReturn(2);
        });

        Sanctum::actingAs($viewer);

        $this->postJson('/api/views', ['type' => 'post', 'id' => $post->id])
            ->assertOk();
    }

    public function test_following_a_user_bumps_recommendation_version(): void
    {
        Notification::fake();

        $viewer = User::factory()->create([
            'username' => 'viewer',
        ]);
        $target = User::factory()->create([
            'username' => 'target-user',
        ]);

        $this->mock(RecommendationCacheService::class, function (MockInterface $mock) use ($viewer): void {
            $mock->shouldReceive('bumpUserVersion')->once()->with($viewer->id)->andReturn(2);
        });

        Sanctum::actingAs($viewer);

        $this->postJson('/api/users/' . $target->username . '/follow')
            ->assertOk();
    }

    public function test_updating_profile_tags_bumps_recommendation_version(): void
    {
        $viewer = User::factory()->create();
        $profile = Profile::create(['user_id' => $viewer->id]);
        $tag = Tag::create(['name' => 'php']);

        $this->mock(RecommendationCacheService::class, function (MockInterface $mock) use ($viewer): void {
            $mock->shouldReceive('bumpUserVersion')->once()->with($viewer->id)->andReturn(2);
        });

        Sanctum::actingAs($viewer);

        $this->postJson('/api/updateprofile/tags/' . $profile->id, ['tags' => [$tag->id]])
            ->assertOk();
    }

    public function test_liking_a_comment_bumps_recommendation_version(): void
    {
        $viewer = User::factory()->create();
        $post = $this->makePublishedPost();
        $comment = Comment::create([
            'user_id' => $post->user_id,
            'post_id' => $post->id,
            'body' => 'Interesting',
        ]);

        Event::fake([CommentLiked::class]);

        $this->mock(RecommendationCacheService::class, function (MockInterface $mock) use ($viewer): void {
            $mock->shouldReceive('bumpUserVersion')->once()->with($viewer->id)->andReturn(2);
        });

        Sanctum::actingAs($viewer);

        $this->postJson('/api/comments/' . $comment->id . '/like')->assertOk();
    }

    private function makePublishedPost(): Post
    {
        $author = User::factory()->create();

        return Post::factory()->create([
            'user_id' => $author->id,
            'is_published' => true,
        ]);
    }
}