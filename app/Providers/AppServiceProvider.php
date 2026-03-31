<?php

namespace App\Providers;

use App\Models\Blog;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Relations\Relation;
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
    }
}
