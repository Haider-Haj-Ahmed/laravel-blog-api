<?php

namespace App\Listeners;

use App\Events\BlogCommented;
use App\Events\BlogLiked;
use App\Events\PostCommented;
use App\Events\PostLiked;
use App\Services\ActivityService;

class LogContentEventActivity
{
    public function __construct(private readonly ActivityService $activityService)
    {
    }

    public function handle(PostLiked|BlogLiked|PostCommented|BlogCommented $event): void
    {
        if (! $event->actor) {
            return;
        }

        match (true) {
            $event instanceof PostLiked => $this->activityService->logUserInteraction(
                $event->actor,
                $event->post,
                'post_liked'
            ),
            $event instanceof BlogLiked => $this->activityService->logUserInteraction(
                $event->actor,
                $event->blog,
                'blog_liked'
            ),
            $event instanceof PostCommented => $this->activityService->logUserInteraction(
                $event->actor,
                $event->post,
                'post_commented',
                ['comment_id' => $event->comment->id]
            ),
            $event instanceof BlogCommented => $this->activityService->logUserInteraction(
                $event->actor,
                $event->blog,
                'blog_commented',
                ['comment_id' => $event->comment->id]
            ),
        };
    }
}
