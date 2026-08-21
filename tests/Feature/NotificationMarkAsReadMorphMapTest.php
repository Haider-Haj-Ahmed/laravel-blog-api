<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Notifications\PostLikedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationMarkAsReadMorphMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_as_read_finds_notification_created_via_notify(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);

        $owner->notify(new PostLikedNotification($post, $actor));

        $notification = DatabaseNotification::query()->latest()->first();

        $this->assertSame('user', $notification->notifiable_type);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk();

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }
}
