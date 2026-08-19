<?php

namespace App\Traits;

use App\Models\Comment;
use App\Models\User;
use App\Notifications\MentionedInComment;
use App\Services\UserSettingsService;

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
            $settingsService = app(UserSettingsService::class);
            foreach($newlyMentionedUsers as $user){
                if (! $settingsService->shouldNotify($user, 'mentions')) {
                    continue;
                }

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
            $settingsService = app(UserSettingsService::class);
            foreach ($mentionedUsers as $user) {
                if (! $settingsService->shouldNotify($user, 'mentions')) {
                    continue;
                }

                $user->notify(new MentionedInComment($comment));
            }
        }
    }
}
