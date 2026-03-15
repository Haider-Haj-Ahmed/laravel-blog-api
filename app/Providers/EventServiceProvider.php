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
        ],
        CommentDisliked::class => [
            DeductPointsForDislike::class,
        ],
        CommentVerified::class => [
            AwardPointsForVerification::class,
        ],
        CommentHighlighted::class => [
            AwardPointsForHighlight::class,
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
