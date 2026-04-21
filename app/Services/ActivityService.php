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
            'owner_user_id' => $actor->id,
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

    public function purgeUserInteraction(User $actor, Model $subject, string $action): int
    {
        return Activity::query()
            ->where('owner_user_id', $actor->id)
            ->where('action', $action)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->delete();
    }

    public function purgeActivitiesForDeletedComment(Comment $comment): int
    {
        $directCommentActivitiesDeleted = Activity::query()
            ->where('subject_type', $comment->getMorphClass())
            ->where('subject_id', $comment->getKey())
            ->delete();

        $contentCommentActivitiesDeleted = Activity::query()
            ->whereIn('action', ['post_commented', 'blog_commented'])
            ->where(function ($query) use ($comment) {
                $query->where('meta->comment_id', $comment->id)
                    ->orWhere('meta->comment_id', (string) $comment->id);
            })
            ->delete();

        return $directCommentActivitiesDeleted + $contentCommentActivitiesDeleted;
    }
}
