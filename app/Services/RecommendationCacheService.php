<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RecommendationCacheService
{
    public function userVersion(int $userId): int
    {
        $key = $this->userVersionKey($userId);

        if (! Cache::has($key)) {
            Cache::forever($key, 1);
            return 1;
        }

        return (int) Cache::get($key, 1);
    }

    public function bumpUserVersion(int $userId): int
    {
        $key = $this->userVersionKey($userId);

        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }

        return (int) Cache::increment($key);
    }

    public function interestKey(int $userId, int $version): string
    {
        return sprintf(
            'rec:%s:user:%d:interest:v%d',
            $this->keyVersion(),
            $userId,
            $version
        );
    }

    public function feedKey(int $userId, int $version, int $page, int $perPage): string
    {
        return sprintf(
            'rec:%s:user:%d:feed:v%d:p%d:pp%d',
            $this->keyVersion(),
            $userId,
            $version,
            $page,
            $perPage
        );
    }

    public function trendingKey(int $limit): string
    {
        return sprintf(
            'rec:%s:trending:posts:limit:%d',
            $this->keyVersion(),
            $limit
        );
    }

    private function userVersionKey(int $userId): string
    {
        return sprintf('rec:%s:user:%d:version', $this->keyVersion(), $userId);
    }

    private function keyVersion(): string
    {
        return (string) config('recommendation.cache.key_version', 'v1');
    }
}
