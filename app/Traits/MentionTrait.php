<?php

namespace App\Traits;

use App\Models\Comment;
use App\Models\User;
use App\Notifications\MentionedInComment;

trait MentionTrait
{
    public function handleMentions(Comment $comment,$oldUsernames=null)
    {
        preg_match_all('/@([\w\-]+)/', $comment->body, $matches);

        $usernames = $matches[1] ?? [];
        if($oldUsernames){
            if(count($usernames)>0){
            $mentioned=User::whereIn('username', $usernames)->get();
            $comment->mentions()->sync($mentioned->pluck('id'));
            $usernames = array_diff($usernames, $oldUsernames);
            if(count($usernames)>0){
            $newlyMentionedUsers = User::whereIn('username', $usernames)->get();
            foreach($newlyMentionedUsers as $user){
                $user->notify(new MentionedInComment($comment));
            }
            }
            }else{
                $comment->mentions()->sync([]);
            }
        }
        else if (count($usernames)) {
            $mentionedUsers = User::whereIn('username', $usernames)->get();
            $comment->mentions()->sync($mentionedUsers->pluck('id'));

            // إرسال إشعار لكل مستخدم مذكور
            foreach ($mentionedUsers as $user) {
                $user->notify(new MentionedInComment($comment));
            }
        }
    }
}
