<?php

namespace App\Listeners;

use App\Events\CommentLiked;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AwardPointsForLike
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CommentLiked $event): void
    {
        $comment = $event->comment;
        $profile = $comment->user->profile;

        if ($profile) {
            $profile->increment('ranking_points', 10);
        }
    }
}
