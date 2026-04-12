<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class PostCommentedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Post $post,
        private readonly Comment $comment,
        private readonly ?User $actor = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'post_commented',
            'title' => 'New comment on your post',
            'body' => ($this->actor?->username ?? 'Someone') . ' commented on your post.',
            'actor' => $this->actor ? [
                'id' => $this->actor->id,
                'username' => $this->actor->username,
                'name' => $this->actor->name,
            ] : null,
            'entity' => [
                'type' => 'post',
                'id' => $this->post->id,
            ],
            'context' => [
                'comment_id' => $this->comment->id,
                'comment_body' => $this->comment->body,
                'post_title' => $this->post->title,
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
