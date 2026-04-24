<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class HighlightedCommentUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
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
            'type' => 'highlighted_comment_updated',
            'title' => 'Highlighted comment updated',
            'body' => 'A highlighted comment on your content was updated.',
            'actor' => $this->actor ? [
                'id' => $this->actor->id,
                'username' => $this->actor->username,
                'name' => $this->actor->name,
            ] : null,
            'entity' => [
                'type' => 'comment',
                'id' => $this->comment->id,
            ],
            'context' => [
                'post_id' => $this->comment->post_id,
                'blog_id' => $this->comment->blog_id,
                'comment_body' => $this->comment->body,
                'is_modified' => true,
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
