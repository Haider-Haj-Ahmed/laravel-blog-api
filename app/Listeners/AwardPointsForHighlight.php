<?php

namespace App\Listeners;

use App\Events\CommentHighlighted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AwardPointsForHighlight
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
    public function handle(CommentHighlighted $event): void
    {
        $comment = $event->comment;
        $profile = $comment->user->profile;

        if ($profile) {
            $profile->increment('ranking_points', 20);
        }
    }
}
