<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\CommentLiked;
use App\Events\CommentDisliked;
use App\Events\CommentVerified;
use App\Events\CommentHighlighted;
use App\Events\CommentUnhighlighted;
use App\Events\PostLiked;
use App\Events\BlogLiked;
use App\Events\PostCommented;
use App\Events\BlogCommented;
use App\Listeners\AwardPointsForLike;
use App\Listeners\DeductPointsForDislike;
use App\Listeners\AwardPointsForVerification;
use App\Listeners\AwardPointsForHighlight;
use App\Listeners\DeductPointsForUnhighlight;
use App\Listeners\LogCommentEventActivity;
use App\Listeners\SendCommentEventNotification;
use App\Listeners\LogContentEventActivity;
use App\Listeners\SendContentEventNotification;

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
            SendCommentEventNotification::class,
        ],
        CommentDisliked::class => [
            DeductPointsForDislike::class,
            LogCommentEventActivity::class,
            SendCommentEventNotification::class,
        ],
        CommentVerified::class => [
            AwardPointsForVerification::class,
            LogCommentEventActivity::class,
            SendCommentEventNotification::class,
        ],
        CommentHighlighted::class => [
            AwardPointsForHighlight::class,
            LogCommentEventActivity::class,
            SendCommentEventNotification::class,
        ],
        CommentUnhighlighted::class => [
            DeductPointsForUnhighlight::class,
            LogCommentEventActivity::class,
        ],
        PostLiked::class => [
            LogContentEventActivity::class,
            SendContentEventNotification::class,
        ],
        BlogLiked::class => [
            LogContentEventActivity::class,
            SendContentEventNotification::class,
        ],
        PostCommented::class => [
            LogContentEventActivity::class,
            SendContentEventNotification::class,
        ],
        BlogCommented::class => [
            LogContentEventActivity::class,
            SendContentEventNotification::class,
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
