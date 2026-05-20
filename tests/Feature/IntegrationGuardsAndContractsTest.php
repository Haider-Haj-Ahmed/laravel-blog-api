<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\User;
use App\Models\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntegrationGuardsAndContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_viewers_endpoint_is_owner_only(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $viewer = User::factory()->create();

        $blog = Blog::create([
            'user_id' => $owner->id,
            'title' => 'Owner blog',
            'subtitle' => 'Owner subtitle',
            'is_published' => true,
        ]);

        View::create([
            'user_id' => $viewer->id,
            'viewable_type' => $blog->getMorphClass(),
            'viewable_id' => $blog->id,
        ]);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/blogs/viewers/{$blog->id}")
            ->assertForbidden()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'You are not authorized to view blog viewers');

        Sanctum::actingAs($owner);

        $this->getJson("/api/blogs/viewers/{$blog->id}")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.viewers.0.id', $viewer->id);
    }

    public function test_mark_notification_as_read_returns_trait_not_found_shape_when_missing(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->patchJson('/api/notifications/non-existent-id/read')
            ->assertNotFound()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Notification not found');
    }

    public function test_mark_notification_as_read_still_marks_owned_notification(): void
    {
        $user = User::factory()->create();

        $notification = DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'Tests\\Notifications\\DummyNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'hello'],
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Notification marked as read');

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }
}
