<?php

namespace Tests\Unit;

use App\Services\RecommendationCacheService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RecommendationCacheServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_generates_keys_and_bumps_version(): void
    {
        $service = app(RecommendationCacheService::class);

        $this->assertSame(1, $service->userVersion(17));
        $this->assertSame('rec:v1:user:17:interest:v1', $service->interestKey(17, 1));
        $this->assertSame('rec:v1:user:17:feed:v1:p2:pp15', $service->feedKey(17, 1, 2, 15));
        $this->assertSame('rec:v1:trending:posts:limit:120', $service->trendingKey(120));

        $this->assertSame(2, $service->bumpUserVersion(17));
        $this->assertSame(2, $service->userVersion(17));
    }
}