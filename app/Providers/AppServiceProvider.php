<?php

namespace App\Providers;

use App\Models\Blog;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'post' => Post::class,
            'blog' => Blog::class,
            'comment' => Comment::class,
        ]);

        RateLimiter::for('recommended-feed', function (Request $request) {
            $maxAttempts = max(1, (int) config('recommendation.rate_limit.per_minute', 30));

            return Limit::perMinute($maxAttempts)
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
