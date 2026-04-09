<?php

namespace App\Listeners;

use App\Events\BlogCommented;
use App\Events\BlogLiked;
use App\Events\PostCommented;
use App\Events\PostLiked;
use App\Notifications\BlogCommentedNotification;
use App\Notifications\BlogLikedNotification;
use App\Notifications\PostCommentedNotification;
use App\Notifications\PostLikedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendContentEventNotification implements ShouldQueue
{
    public function handle(PostLiked|BlogLiked|PostCommented|BlogCommented $event): void
    {
        $owner = match (true) {
            $event instanceof PostLiked => $event->post->user,
            $event instanceof BlogLiked => $event->blog->user,
            $event instanceof PostCommented => $event->post->user,
            $event instanceof BlogCommented => $event->blog->user,
        };

        $actor = $event->actor;
        if (! $owner || ($actor && $owner->id === $actor->id)) {
            return;
        }

        $notification = match (true) {
            $event instanceof PostLiked => new PostLikedNotification($event->post, $actor),
            $event instanceof BlogLiked => new BlogLikedNotification($event->blog, $actor),
            $event instanceof PostCommented => new PostCommentedNotification($event->post, $event->comment, $actor),
            $event instanceof BlogCommented => new BlogCommentedNotification($event->blog, $event->comment, $actor),
        };

        $owner->notify($notification);
    }
}
