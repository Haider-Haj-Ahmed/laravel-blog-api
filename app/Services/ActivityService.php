<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityService
{
    public function logUserInteraction(User $actor, Model $subject, string $action, array $meta = []): Activity
    {
        return Activity::create([
            'owner_user_id' => $subject->user_id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'meta' => $meta,
        ]);
    }

    public function logCommentEvent(Comment $comment, string $action, ?User $actor = null, array $meta = []): Activity
    {
        return Activity::create([
            'owner_user_id' => $comment->user_id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $comment->getMorphClass(),
            'subject_id' => $comment->getKey(),
            'meta' => $meta,
        ]);
    }
}
