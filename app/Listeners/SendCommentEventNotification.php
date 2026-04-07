<?php

namespace App\Listeners;

use App\Events\CommentDisliked;
use App\Events\CommentHighlighted;
use App\Events\CommentLiked;
use App\Events\CommentVerified;
use App\Notifications\CommentDislikedNotification;
use App\Notifications\CommentHighlightedNotification;
use App\Notifications\CommentLikedNotification;
use App\Notifications\CommentVerifiedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCommentEventNotification implements ShouldQueue
{
    public function handle(CommentLiked|CommentDisliked|CommentHighlighted|CommentVerified $event): void
    {
        $commentOwner = $event->comment->user;
        $actor = $event->actor;

        if (! $commentOwner || ($actor && $commentOwner->id === $actor->id)) {
            return;
        }

        $notification = match (true) {
            $event instanceof CommentLiked => new CommentLikedNotification($event->comment, $actor),
            $event instanceof CommentDisliked => new CommentDislikedNotification($event->comment, $actor),
            $event instanceof CommentHighlighted => new CommentHighlightedNotification($event->comment, $actor),
            $event instanceof CommentVerified => new CommentVerifiedNotification($event->comment, $actor),
        };

        $commentOwner->notify($notification);
    }
}
