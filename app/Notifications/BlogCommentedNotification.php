<?php

namespace App\Notifications;

use App\Models\Blog;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class BlogCommentedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Blog $blog,
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
            'type' => 'blog_commented',
            'title' => 'New comment on your blog',
            'body' => ($this->actor?->username ?? 'Someone') . ' commented on your blog.',
            'actor' => $this->actor ? [
                'id' => $this->actor->id,
                'username' => $this->actor->username,
                'name' => $this->actor->name,
            ] : null,
            'entity' => [
                'type' => 'blog',
                'id' => $this->blog->id,
            ],
            'context' => [
                'comment_id' => $this->comment->id,
                'comment_body' => $this->comment->body,
                'blog_title' => $this->blog->title,
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
