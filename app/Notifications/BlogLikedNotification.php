<?php

namespace App\Notifications;

use App\Models\Blog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class BlogLikedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Blog $blog,
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
            'type' => 'blog_liked',
            'title' => 'Blog liked',
            'body' => ($this->actor?->username ?? 'Someone') . ' liked your blog.',
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
                'blog_title' => $this->blog->title,
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
