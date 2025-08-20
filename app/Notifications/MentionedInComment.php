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

    protected $comment;

    public function __construct(Comment $comment)
    {
        $this->comment = $comment;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'comment_id' => $this->comment->id,
            'body' => $this->comment->body,
            'user' => [
                'id' => $this->comment->user->id,
                'username' => $this->comment->user->username,
            ],
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'comment_id' => $this->comment->id,
            'body' => $this->comment->body,
            'user' => [
                'id' => $this->comment->user->id,
                'username' => $this->comment->user->username,
            ],
        ]);
    }
}
