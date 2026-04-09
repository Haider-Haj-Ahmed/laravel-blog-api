<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class UserFollowedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly User $follower)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'user_followed',
            'title' => 'New follower',
            'body' => "{$this->follower->username} started following you.",
            'actor' => [
                'id' => $this->follower->id,
                'username' => $this->follower->username,
                'name' => $this->follower->name,
            ],
            'entity' => [
                'type' => 'user',
                'id' => $this->follower->id,
            ],
            'context' => [],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
