<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Models\View;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PostRecommendationService
{
    public function __construct(private readonly RecommendationCacheService $cacheKeys)
    {
    }

    public function buildFeed(User $user, int $page = 1, ?int $perPage = null): LengthAwarePaginator
    {
        $page = max(1, $page);

        $defaultPerPage = (int) config('recommendation.feed.default_per_page', 15);
        $maxPerPage = (int) config('recommendation.feed.max_per_page', 30);
        $perPage = min(max(1, $perPage ?? $defaultPerPage), $maxPerPage);

        $userVersion = $this->cacheKeys->userVersion($user->id);

        $feedCacheEnabled = (bool) config('recommendation.cache.feed.enabled', true);
        $feedTtlSeconds = max(1, (int) config('recommendation.cache.feed.ttl_seconds', 60));

        if ($feedCacheEnabled) {
            $cacheKey = $this->cacheKeys->feedKey($user->id, $userVersion, $page, $perPage);

            $payload = $this->rememberWithOptionalLock(
                $cacheKey,
                $feedTtlSeconds,
                function () use ($user, $page, $perPage, $userVersion) {
                    return $this->buildFeedPayload($user, $page, $perPage, $userVersion);
                }
            );
        } else {
            $payload = $this->buildFeedPayload($user, $page, $perPage, $userVersion);
        }

        $postIds = collect($payload['post_ids'] ?? [])->map(fn ($id) => (int) $id)->values();
        $total = (int) ($payload['total'] ?? 0);

        if ($postIds->isEmpty()) {
            $slice = collect();
        } else {
            $postsById = Post::query()
                ->whereIn('id', $postIds)
                ->with(['user', 'photos', 'tags'])
                ->withCount(['likes', 'comments', 'saves', 'views'])
                ->get()
                ->keyBy('id');

            $ordered = $postIds
                ->map(fn (int $id) => $postsById->get($id))
                ->filter()
                ->values();

            $slice = $ordered->forPage($page, $perPage)->values();
        }

        return new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    private function buildFeedPayload(User $user, int $page, int $perPage, int $userVersion): array
    {
        $targetCount = $page * $perPage;
        $bucketPoolLimit = max((int) config('recommendation.feed.candidate_pool_per_bucket', 120), $targetCount * 3);

        $followedIds = $user->following()->pluck('users.id')->values();
        $followedIdSet = array_flip($followedIds->all());

        ['tagScores' => $tagScores, 'topTagIds' => $topTagIds] = $this->getUserInterestData($user, $userVersion);

        $excludeViewed = (bool) config('recommendation.feed.hide_previously_viewed_posts', true);
        $seenPostIds = $excludeViewed
            ? $this->getViewedPostIds($user)
            : collect();

        $bucketA = $this->buildBucketFollowedWithInterest(
            $user,
            $topTagIds,
            $followedIds,
            $seenPostIds,
            $bucketPoolLimit,
            $tagScores,
            $followedIdSet,
            true
        );

        $bucketB = $this->buildBucketNonFollowedWithInterest(
            $user,
            $topTagIds,
            $followedIds,
            $seenPostIds,
            $bucketPoolLimit,
            $tagScores,
            $followedIdSet
        );

        $bucketC = $this->buildTrendingBucket(
            $user,
            $seenPostIds,
            $bucketPoolLimit,
            $tagScores,
            $followedIdSet
        );

        $feedCollection = $this->blendBuckets(
            $bucketA,
            $bucketB,
            $bucketC,
            $targetCount
        );

        return [
            'post_ids' => $feedCollection->pluck('id')->values()->all(),
            'total' => $feedCollection->count(),
        ];
    }

    private function getUserInterestData(User $user, int $userVersion): array
    {
        $interestCacheEnabled = (bool) config('recommendation.cache.interest.enabled', true);

        if (! $interestCacheEnabled) {
            $tagScores = $this->buildUserTagScores($user);

            return [
                'tagScores' => $tagScores,
                'topTagIds' => $this->selectTopTagIds($tagScores),
            ];
        }

        $ttlMinutes = max(1, (int) config('recommendation.cache.interest.ttl_minutes', 15));
        $cacheKey = $this->cacheKeys->interestKey($user->id, $userVersion);

        $payload = $this->rememberWithOptionalLock($cacheKey, $ttlMinutes * 60, function () use ($user) {
            $tagScores = $this->buildUserTagScores($user);

            return [
                'scores' => $tagScores->all(),
                'top_tag_ids' => $this->selectTopTagIds($tagScores)->values()->all(),
            ];
        });

        return [
            'tagScores' => collect($payload['scores'] ?? [])->map(fn ($score) => (float) $score),
            'topTagIds' => collect($payload['top_tag_ids'] ?? [])->map(fn ($id) => (int) $id),
        ];
    }

    private function rememberWithOptionalLock(string $cacheKey, int $ttlSeconds, callable $resolver): mixed
    {
        $existing = Cache::get($cacheKey);
        if ($existing !== null) {
            return $existing;
        }

        $lockSeconds = max(1, (int) config('recommendation.cache.lock_seconds', 5));

        $lock = Cache::lock($cacheKey . ':lock', $lockSeconds);

        return $lock->block($lockSeconds, function () use ($cacheKey, $ttlSeconds, $resolver) {
            $again = Cache::get($cacheKey);
            if ($again !== null) {
                return $again;
            }

            $value = $resolver();
            Cache::put($cacheKey, $value, now()->addSeconds($ttlSeconds));

            return $value;
        });
    }

    private function buildUserTagScores(User $user): Collection
    {
        $scores = collect();

        $weights = config('recommendation.interest.weights', []);
        $viewWeight = (float) ($weights['view'] ?? 1.0);
        $likeWeight = (float) ($weights['like'] ?? 3.0);
        $commentWeight = (float) ($weights['comment'] ?? 4.0);
        $saveWeight = (float) ($weights['save'] ?? 5.0);
        $profilePriorWeight = (float) ($weights['profile_tag_prior'] ?? 2.5);

        if ($user->profile) {
            $profileTagIds = $user->profile->tags()->pluck('tags.id');
            foreach ($profileTagIds as $tagId) {
                $scores[$tagId] = ($scores[$tagId] ?? 0.0) + $profilePriorWeight;
            }
        }

        $this->accumulateTagScoresFromRows(
            $scores,
            DB::table('likes')
                ->join('post_tag', 'likes.post_id', '=', 'post_tag.post_id')
                ->where('likes.user_id', $user->id)
                ->get(['post_tag.tag_id as tag_id', 'likes.created_at as created_at']),
            $likeWeight
        );

        $this->accumulateTagScoresFromRows(
            $scores,
            DB::table('comments')
                ->join('post_tag', 'comments.post_id', '=', 'post_tag.post_id')
                ->where('comments.user_id', $user->id)
                ->whereNotNull('comments.post_id')
                ->get(['post_tag.tag_id as tag_id', 'comments.created_at as created_at']),
            $commentWeight
        );

        $postMorphClass = (new Post())->getMorphClass();

        $this->accumulateTagScoresFromRows(
            $scores,
            DB::table('views')
                ->join('post_tag', 'views.viewable_id', '=', 'post_tag.post_id')
                ->where('views.user_id', $user->id)
                ->where('views.viewable_type', $postMorphClass)
                ->get(['post_tag.tag_id as tag_id', 'views.created_at as created_at']),
            $viewWeight
        );

        $this->accumulateTagScoresFromRows(
            $scores,
            DB::table('saves')
                ->join('post_tag', 'saves.saveable_id', '=', 'post_tag.post_id')
                ->where('saves.user_id', $user->id)
                ->where('saves.saveable_type', $postMorphClass)
                ->get(['post_tag.tag_id as tag_id', 'saves.created_at as created_at']),
            $saveWeight
        );

        return $scores->sortDesc();
    }

    private function accumulateTagScoresFromRows(Collection $scores, Collection $rows, float $eventWeight): void
    {
        foreach ($rows as $row) {
            $tagId = (int) $row->tag_id;
            $createdAt = $row->created_at;
            $multiplier = $this->decayMultiplier($createdAt);
            $scores[$tagId] = ($scores[$tagId] ?? 0.0) + ($eventWeight * $multiplier);
        }
    }

    private function decayMultiplier(CarbonInterface|string|null $createdAt): float
    {
        if (! $createdAt) {
            return 1.0;
        }

        $when = $createdAt instanceof CarbonInterface ? $createdAt : Carbon::parse($createdAt);
        $days = $when->diffInDays(now());

        $decay = config('recommendation.interest.decay', []);

        if ($days <= 7) {
            return (float) ($decay['days_7'] ?? 1.0);
        }

        if ($days <= 30) {
            return (float) ($decay['days_30'] ?? 0.7);
        }

        if ($days <= 90) {
            return (float) ($decay['days_90'] ?? 0.4);
        }

        return 0.2;
    }

    private function selectTopTagIds(Collection $tagScores): Collection
    {
        $minTop = (int) config('recommendation.interest.min_top_tags', 3);
        $maxTop = (int) config('recommendation.interest.max_top_tags', 8);
        $coverageTarget = (float) config('recommendation.interest.coverage_target', 0.75);

        $total = (float) $tagScores->sum();
        if ($total <= 0) {
            return collect();
        }

        $selected = collect();
        $covered = 0.0;

        foreach ($tagScores as $tagId => $score) {
            if ($selected->count() >= $maxTop) {
                break;
            }

            $selected->push((int) $tagId);
            $covered += (float) $score;

            $coverage = $covered / $total;
            if ($selected->count() >= $minTop && $coverage >= $coverageTarget) {
                break;
            }
        }

        return $selected;
    }

    private function getViewedPostIds(User $user): Collection
    {
        return View::query()
            ->where('user_id', $user->id)
            ->where('viewable_type', (new Post())->getMorphClass())
            ->pluck('viewable_id')
            ->values();
    }

    private function buildBucketFollowedWithInterest(
        User $user,
        Collection $topTagIds,
        Collection $followedIds,
        Collection $seenPostIds,
        int $limit,
        Collection $tagScores,
        array $followedIdSet,
        bool $forceFollowBoost = false
    ): Collection {
        if ($followedIds->isEmpty()) {
            return collect();
        }

        $query = Post::query()
            ->where('is_published', true)
            ->whereIn('user_id', $followedIds)
            ->where('user_id', '!=', $user->id)
            ->when($seenPostIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $seenPostIds))
            ->with(['user', 'photos', 'tags'])
            ->withCount(['likes', 'comments', 'saves', 'views'])
            ->latest()
            ->limit($limit);

        if ($topTagIds->isNotEmpty()) {
            $query->whereHas('tags', function ($q) use ($topTagIds) {
                $q->whereIn('tags.id', $topTagIds);
            });
        }

        return $this->rankPostsCollection($query->get(), $tagScores, $followedIdSet, $forceFollowBoost);
    }

    private function buildBucketNonFollowedWithInterest(
        User $user,
        Collection $topTagIds,
        Collection $followedIds,
        Collection $seenPostIds,
        int $limit,
        Collection $tagScores,
        array $followedIdSet
    ): Collection {
        $blockedAuthorIds = $followedIds->push($user->id)->unique();

        $query = Post::query()
            ->where('is_published', true)
            ->whereNotIn('user_id', $blockedAuthorIds)
            ->when($seenPostIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $seenPostIds))
            ->with(['user', 'photos', 'tags'])
            ->withCount(['likes', 'comments', 'saves', 'views'])
            ->latest()
            ->limit($limit);

        if ($topTagIds->isNotEmpty()) {
            $query->whereHas('tags', function ($q) use ($topTagIds) {
                $q->whereIn('tags.id', $topTagIds);
            });
        }

        return $this->rankPostsCollection($query->get(), $tagScores, $followedIdSet, false);
    }

    private function buildTrendingBucket(
        User $user,
        Collection $seenPostIds,
        int $limit,
        Collection $tagScores,
        array $followedIdSet
    ): Collection {
        $windowDays = (int) config('recommendation.trending.window_days', 7);
        $weights = config('recommendation.trending.weights', []);
        $wl = (float) ($weights['likes'] ?? 3.0);
        $wc = (float) ($weights['comments'] ?? 4.0);
        $ws = (float) ($weights['saves'] ?? 6.0);
        $wv = (float) ($weights['views'] ?? 1.0);

        $trendingCacheEnabled = (bool) config('recommendation.cache.trending.enabled', true);

        if ($trendingCacheEnabled) {
            $ttlMinutes = max(1, (int) config('recommendation.cache.trending.ttl_minutes', 3));
            $cacheKey = $this->cacheKeys->trendingKey($limit);

            $candidateIds = $this->rememberWithOptionalLock($cacheKey, $ttlMinutes * 60, function () use ($windowDays, $wl, $wc, $ws, $wv, $limit) {
                return Post::query()
                    ->where('is_published', true)
                    ->where('created_at', '>=', now()->subDays($windowDays))
                    ->withCount(['likes', 'comments', 'saves', 'views'])
                    ->orderByRaw("(likes_count * {$wl} + comments_count * {$wc} + saves_count * {$ws} + views_count * {$wv}) DESC")
                    ->latest()
                    ->limit($limit * 2)
                    ->pluck('id')
                    ->values()
                    ->all();
            });

            if (empty($candidateIds)) {
                return collect();
            }

            $query = Post::query()
                ->whereIn('id', $candidateIds)
                ->where('user_id', '!=', $user->id)
                ->when($seenPostIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $seenPostIds))
                ->with(['user', 'photos', 'tags'])
                ->withCount(['likes', 'comments', 'saves', 'views'])
                ->limit($limit);

            return $this->rankPostsCollection($query->get(), $tagScores, $followedIdSet, false);
        }

        $query = Post::query()
            ->where('is_published', true)
            ->where('user_id', '!=', $user->id)
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->when($seenPostIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $seenPostIds))
            ->with(['user', 'photos', 'tags'])
            ->withCount(['likes', 'comments', 'saves', 'views'])
            ->orderByRaw("(likes_count * {$wl} + comments_count * {$wc} + saves_count * {$ws} + views_count * {$wv}) DESC")
            ->latest()
            ->limit($limit);

        return $this->rankPostsCollection($query->get(), $tagScores, $followedIdSet, false);
    }

    private function rankPostsCollection(
        Collection $posts,
        Collection $tagScores,
        array $followedIdSet,
        bool $forceFollowBoost
    ): Collection {
        $ranked = $posts->map(function (Post $post) use ($tagScores, $followedIdSet, $forceFollowBoost) {
            $post->recommendation_score = $this->scorePost($post, $tagScores, $followedIdSet, $forceFollowBoost);
            return $post;
        });

        return $ranked
            ->sortByDesc('recommendation_score')
            ->values();
    }

    private function scorePost(Post $post, Collection $tagScores, array $followedIdSet, bool $forceFollowBoost): float
    {
        $tagAffinity = 0.0;
        foreach ($post->tags as $tag) {
            $tagAffinity += (float) ($tagScores[$tag->id] ?? 0.0);
        }

        $followBoost = 0.0;
        if ($forceFollowBoost || isset($followedIdSet[$post->user_id])) {
            $followBoost = (float) config('recommendation.ranking.follow_boost', 2.0);
        }

        $qualityWeights = config('recommendation.ranking.quality', []);
        $quality =
            ((int) $post->likes_count * (float) ($qualityWeights['likes'] ?? 0.6)) +
            ((int) $post->comments_count * (float) ($qualityWeights['comments'] ?? 0.8)) +
            ((int) $post->saves_count * (float) ($qualityWeights['saves'] ?? 1.0)) +
            ((int) $post->views_count * (float) ($qualityWeights['views'] ?? 0.2));

        $halfLifeHours = max(1.0, (float) config('recommendation.ranking.freshness_half_life_hours', 24));
        $ageHours = max(0.0, (float) $post->created_at->diffInHours(now()));
        $freshness = 1.0 / (1.0 + ($ageHours / $halfLifeHours));

        return $tagAffinity + $followBoost + $quality + $freshness;
    }

    private function blendBuckets(
        Collection $bucketA,
        Collection $bucketB,
        Collection $bucketC,
        int $targetCount
    ): Collection {
        $ratios = config('recommendation.blend', []);
        $ratioA = (float) ($ratios['followed_with_interest'] ?? 0.50);
        $ratioB = (float) ($ratios['non_followed_with_interest'] ?? 0.30);
        $ratioC = (float) ($ratios['trending'] ?? 0.20);

        $quotaA = (int) floor($targetCount * $ratioA);
        $quotaB = (int) floor($targetCount * $ratioB);
        $quotaC = max(0, $targetCount - $quotaA - $quotaB);

        $seenPostIds = [];
        $authorCounts = [];
        $authorCap = max(1, (int) config('recommendation.diversity.max_posts_per_author_per_page', 2));

        $takenA = 0;
        $takenB = 0;
        $takenC = 0;

        $feed = collect();

        while ($feed->count() < $targetCount) {
            $progress = false;

            if ($takenA < $quotaA) {
                $post = $this->takeNextEligiblePost($bucketA, $seenPostIds, $authorCounts, $authorCap);
                if ($post) {
                    $feed->push($post);
                    $takenA++;
                    $progress = true;
                }
            }

            if ($feed->count() >= $targetCount) {
                break;
            }

            if ($takenB < $quotaB) {
                $post = $this->takeNextEligiblePost($bucketB, $seenPostIds, $authorCounts, $authorCap);
                if ($post) {
                    $feed->push($post);
                    $takenB++;
                    $progress = true;
                }
            }

            if ($feed->count() >= $targetCount) {
                break;
            }

            if ($takenC < $quotaC) {
                $post = $this->takeNextEligiblePost($bucketC, $seenPostIds, $authorCounts, $authorCap);
                if ($post) {
                    $feed->push($post);
                    $takenC++;
                    $progress = true;
                }
            }

            if (! $progress) {
                break;
            }
        }

        if ($feed->count() < $targetCount) {
            $leftovers = $bucketA->concat($bucketB)->concat($bucketC)
                ->sortByDesc('recommendation_score')
                ->values();

            while ($feed->count() < $targetCount) {
                $post = $this->takeNextEligiblePost($leftovers, $seenPostIds, $authorCounts, $authorCap);
                if (! $post) {
                    break;
                }

                $feed->push($post);
            }
        }

        return $feed->values();
    }

    private function takeNextEligiblePost(Collection &$bucket, array &$seenPostIds, array &$authorCounts, int $authorCap): ?Post
    {
        if ($bucket->isEmpty()) {
            return null;
        }

        foreach ($bucket as $index => $post) {
            if (isset($seenPostIds[$post->id])) {
                continue;
            }

            $count = $authorCounts[$post->user_id] ?? 0;
            if ($count >= $authorCap) {
                continue;
            }

            $seenPostIds[$post->id] = true;
            $authorCounts[$post->user_id] = $count + 1;
            $bucket->forget($index);

            return $post;
        }

        return null;
    }
}
