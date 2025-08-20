<?php

namespace App\Traits;

use App\Models\Comment;
use App\Models\User;
use App\Notifications\MentionedInComment;

trait MentionTrait
{
    public function handleMentions(Comment $comment)
    {
        preg_match_all('/@([\w\-]+)/', $comment->body, $matches);

        $usernames = $matches[1] ?? [];

        if (count($usernames)) {
            $mentionedUsers = User::whereIn('username', $usernames)->get();
            $comment->mentions()->sync($mentionedUsers->pluck('id'));

            // إرسال إشعار لكل مستخدم مذكور
            foreach ($mentionedUsers as $user) {
                $user->notify(new MentionedInComment($comment));
            }
        }
    }
}
