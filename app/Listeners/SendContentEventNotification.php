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
use App\Services\UserSettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendContentEventNotification implements ShouldQueue
{
    public function __construct(private readonly UserSettingsService $settingsService) {}

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

        $eventKey = match (true) {
            $event instanceof PostLiked => 'likes',
            $event instanceof BlogLiked => 'likes',
            $event instanceof PostCommented => 'comments',
            $event instanceof BlogCommented => 'comments',
        };

        if (! $this->settingsService->shouldNotify($owner, $eventKey)) {
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
