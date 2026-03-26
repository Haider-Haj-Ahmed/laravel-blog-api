<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\CommentLiked;
use App\Events\CommentDisliked;
use App\Events\CommentVerified;
use App\Events\CommentHighlighted;
use App\Listeners\AwardPointsForLike;
use App\Listeners\DeductPointsForDislike;
use App\Listeners\AwardPointsForVerification;
use App\Listeners\AwardPointsForHighlight;
use App\Listeners\LogCommentEventActivity;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        CommentLiked::class => [
            AwardPointsForLike::class,
            LogCommentEventActivity::class,
        ],
        CommentDisliked::class => [
            DeductPointsForDislike::class,
            LogCommentEventActivity::class,
        ],
        CommentVerified::class => [
            AwardPointsForVerification::class,
            LogCommentEventActivity::class,
        ],
        CommentHighlighted::class => [
            AwardPointsForHighlight::class,
            LogCommentEventActivity::class,
        ],
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
