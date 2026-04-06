<?php

namespace Tests\Feature;

use App\Models\Like;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Tag;
use App\Models\User;
use App\Models\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecommendedFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('recommendation.blend', [
            'followed_with_interest' => 0.5,
            'non_followed_with_interest' => 0.5,
            'trending' => 0.0,
        ]);
        config()->set('recommendation.feed.hide_previously_viewed_posts', true);
        config()->set('recommendation.cache.feed.enabled', true);
        config()->set('recommendation.cache.interest.enabled', true);
        config()->set('recommendation.cache.trending.enabled', true);
    }

    public function test_recommended_feed_prioritizes_followed_posts_and_hides_viewed_posts(): void
    {
        $tag = Tag::create(['name' => 'laravel']);

        $viewer = User::factory()->create([
            'name' => 'Viewer',
            'username' => 'viewer',
        ]);
        Profile::create(['user_id' => $viewer->id]);
        $viewer->profile->tags()->attach($tag->id);

        $followedAuthor = User::factory()->create([
            'name' => 'Followed Author',
            'username' => 'followed-author',
        ]);
        Profile::create(['user_id' => $followedAuthor->id]);

        $otherAuthor = User::factory()->create([
            'name' => 'Other Author',
            'username' => 'other-author',
        ]);
        Profile::create(['user_id' => $otherAuthor->id]);

        $viewer->following()->attach($followedAuthor->id);

        $followedPost = $this->makeTaggedPost($followedAuthor, $tag, 'Followed post', 1);
        $otherPost = $this->makeTaggedPost($otherAuthor, $tag, 'Other post', 2);
        $viewedPost = $this->makeTaggedPost($otherAuthor, $tag, 'Viewed post', 3);

        Like::create(['user_id' => $viewer->id, 'post_id' => $followedPost->id]);

        View::create([
            'user_id' => $viewer->id,
            'viewable_type' => (new Post())->getMorphClass(),
            'viewable_id' => $viewedPost->id,
        ]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/posts/recommended?per_page=2');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$followedPost->id, $otherPost->id], $ids);
        $this->assertNotContains($viewedPost->id, $ids);
        $this->assertSame(2, $response->json('pagination.total'));
    }

    public function test_recommended_feed_returns_empty_when_user_has_no_signals(): void
    {
        $viewer = User::factory()->create([
            'name' => 'Empty Viewer',
            'username' => 'empty-viewer',
        ]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/posts/recommended');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('pagination.total'));
    }

    private function makeTaggedPost(User $author, Tag $tag, string $title, int $hoursAgo): Post
    {
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'title' => $title,
            'body' => $title . ' body',
            'is_published' => true,
            'created_at' => now()->subHours($hoursAgo),
            'updated_at' => now()->subHours($hoursAgo),
        ]);

        $post->tags()->attach($tag->id);

        return $post;
    }
}