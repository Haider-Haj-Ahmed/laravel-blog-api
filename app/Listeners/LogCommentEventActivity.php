<?php

namespace App\Listeners;

use App\Events\CommentDisliked;
use App\Events\CommentHighlighted;
use App\Events\CommentLiked;
use App\Events\CommentVerified;
use App\Services\ActivityService;

class LogCommentEventActivity
{
    public function __construct(private readonly ActivityService $activityService)
    {
    }

    public function handle(CommentLiked|CommentDisliked|CommentHighlighted|CommentVerified $event): void
    {
        $action = match (true) {
            $event instanceof CommentLiked => 'comment_liked',
            $event instanceof CommentDisliked => 'comment_disliked',
            $event instanceof CommentHighlighted => 'comment_highlighted',
            $event instanceof CommentVerified => 'comment_verified',
        };

        $meta = [];

        if ($event instanceof CommentVerified) {
            $meta['source'] = 'analyzer';
        }

        $this->activityService->logCommentEvent(
            $event->comment,
            $action,
            $event->actor ?? null,
            $meta
        );
    }
}
