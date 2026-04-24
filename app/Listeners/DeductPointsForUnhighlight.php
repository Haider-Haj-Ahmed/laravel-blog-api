<?php

namespace App\Listeners;

use App\Events\CommentUnhighlighted;

class DeductPointsForUnhighlight
{
    public function handle(CommentUnhighlighted $event): void
    {
        $comment = $event->comment;
        $profile = $comment->user->profile;

        if ($profile) {
            $profile->decrement('ranking_points', 20);
        }
    }
}
