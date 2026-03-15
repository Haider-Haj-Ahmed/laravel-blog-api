<?php

namespace App\Listeners;

use App\Events\CommentVerified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AwardPointsForVerification
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
    public function handle(CommentVerified $event): void
    {
        $comment = $event->comment;
        $profile = $comment->user->profile;

        if ($profile) {
            $profile->increment('ranking_points', 15);
        }
    }
}
