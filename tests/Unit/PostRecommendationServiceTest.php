<?php

namespace Tests\Unit;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Save;
use App\Models\Tag;
use App\Models\User;
use App\Models\View;
use App\Services\PostRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Tests\TestCase;

class PostRecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('recommendation.cache.feed.enabled', false);
        config()->set('recommendation.cache.interest.enabled', false);
        config()->set('recommendation.cache.trending.enabled', false);
    }

    public function test_decay_multiplier_covers_all_ranges(): void
    {
        $this->assertSame(1.0, $this->callPrivate('decayMultiplier', [null]));
        $this->assertSame(1.0, $this->callPrivate('decayMultiplier', [now()->subDays(3)]));
        $this->assertSame(0.7, $this->callPrivate('decayMultiplier', [now()->subDays(20)]));
        $this->assertSame(0.4, $this->callPrivate('decayMultiplier', [now()->subDays(60)]));
        $this->assertSame(0.2, $this->callPrivate('decayMultiplier', [now()->subDays(120)]));
    }

    public function test_select_top_tag_ids_respects_coverage_and_returns_empty_for_no_scores(): void
    {
        $empty = $this->callPrivate('selectTopTagIds', [collect()]);
        $this->assertInstanceOf(Collection::class, $empty);
        $this->assertCount(0, $empty);

        config()->set('recommendation.interest.min_top_tags', 1);
        config()->set('recommendation.interest.max_top_tags', 2);
        config()->set('recommendation.interest.coverage_target', 0.5);

        $selected = $this->callPrivate('selectTopTagIds', [collect([10 => 10.0, 11 => 1.0, 12 => 1.0])]);

        $this->assertSame([10], $selected->values()->all());
    }

    public function test_build_user_tag_scores_accumulates_profile_and_interactions(): void
    {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id]);

        $laravel = Tag::create(['name' => 'laravel']);
        $php = Tag::create(['name' => 'php']);
        $user->profile->tags()->attach($laravel->id);

        $postWithLaravel = Post::factory()->create(['is_published' => true]);
        $postWithLaravel->tags()->attach($laravel->id);

        $postWithPhp = Post::factory()->create(['is_published' => true]);
        $postWithPhp->tags()->attach($php->id);

        Like::create(['user_id' => $user->id, 'post_id' => $postWithLaravel->id]);
        Comment::create(['user_id' => $user->id, 'post_id' => $postWithLaravel->id, 'body' => 'Great post']);
        Save::create(['user_id' => $user->id, 'saveable_type' => (new Post())->getMorphClass(), 'saveable_id' => $postWithLaravel->id]);
        View::create(['user_id' => $user->id, 'viewable_type' => (new Post())->getMorphClass(), 'viewable_id' => $postWithLaravel->id]);

        $scores = $this->callPrivate('buildUserTagScores', [$user]);

        $this->assertInstanceOf(Collection::class, $scores);
        $this->assertArrayHasKey($laravel->id, $scores->all());
        $this->assertArrayNotHasKey($php->id, $scores->all());
        $this->assertGreaterThan(0, $scores[$laravel->id]);
    }

    public function test_blend_buckets_caps_authors_and_deduplicates_posts(): void
    {
        config()->set('recommendation.diversity.max_posts_per_author_per_page', 2);

        $authorOne = User::factory()->create();
        $authorTwo = User::factory()->create();

        $postOne = $this->makePost(1, $authorOne->id, 30.0);
        $postTwo = $this->makePost(2, $authorOne->id, 20.0);
        $postThree = $this->makePost(3, $authorOne->id, 10.0);
        $duplicatePostTwo = $this->makePost(2, $authorOne->id, 15.0);
        $postFour = $this->makePost(4, $authorTwo->id, 5.0);

        $result = $this->callPrivate('blendBuckets', [
            collect([$postOne, $postTwo, $postThree]),
            collect([$duplicatePostTwo, $postFour]),
            collect(),
            3,
        ]);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
        $this->assertCount(3, $result->pluck('id')->unique());
        $this->assertSame(2, $result->where('user_id', $authorOne->id)->count());
        $this->assertSame(1, $result->where('user_id', $authorTwo->id)->count());
    }

    private function callPrivate(string $method, array $arguments = []): mixed
    {
        $service = app(PostRecommendationService::class);
        $reflection = new ReflectionClass($service);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($service, $arguments);
    }

    private function makePost(int $id, int $userId, float $score): Post
    {
        $post = new Post();
        $post->id = $id;
        $post->user_id = $userId;
        $post->recommendation_score = $score;

        return $post;
    }
}