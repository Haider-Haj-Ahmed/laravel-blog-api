<?php

namespace App\Listeners;

use App\Events\CommentDisliked;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DeductPointsForDislike
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
    public function handle(CommentDisliked $event): void
    {
        $comment = $event->comment;
        $profile = $comment->user->profile;

        if ($profile) {
            $profile->decrement('ranking_points', 2);
        }
    }
}
