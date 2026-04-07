<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class MentionedInComment extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Comment $comment)
    {
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'comment_mentioned',
            'title' => 'You were mentioned in a comment',
            'body' => "{$this->comment->user->username} mentioned you in a comment.",
            'actor' => [
                'id' => $this->comment->user->id,
                'username' => $this->comment->user->username,
                'name' => $this->comment->user->name,
            ],
            'entity' => [
                'type' => 'comment',
                'id' => $this->comment->id,
            ],
            'context' => [
                'post_id' => $this->comment->post_id,
                'blog_id' => $this->comment->blog_id,
                'comment_body' => $this->comment->body,
            ],
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
