<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\CommentHighlightedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentHighlightToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_owner_can_toggle_comment_highlight_and_points_are_awarded_then_deducted(): void
    {
        Notification::fake();

        $subjectOwner = User::factory()->create();
        Profile::create(['user_id' => $subjectOwner->id, 'ranking_points' => 0]);

        $commentOwner = User::factory()->create();
        $commentOwnerProfile = Profile::create(['user_id' => $commentOwner->id, 'ranking_points' => 0]);

        $post = Post::factory()->create([
            'user_id' => $subjectOwner->id,
            'is_published' => true,
        ]);

        $comment = Comment::create([
            'user_id' => $commentOwner->id,
            'post_id' => $post->id,
            'body' => 'test comment',
        ]);

        Sanctum::actingAs($subjectOwner);

        $highlightResponse = $this->postJson("/api/comments/{$comment->id}/highlight");

        $highlightResponse
            ->assertOk()
            ->assertJsonPath('message', 'Comment highlighted successfully')
            ->assertJsonPath('data.is_highlighted', true);

        $comment->refresh();
        $commentOwnerProfile->refresh();

        $this->assertTrue($comment->is_highlighted);
        $this->assertSame(20, (int) $commentOwnerProfile->ranking_points);
        Notification::assertSentTo($commentOwner, CommentHighlightedNotification::class);

        $this->travel(4)->seconds();

        $unhighlightResponse = $this->postJson("/api/comments/{$comment->id}/highlight");

        $unhighlightResponse
            ->assertOk()
            ->assertJsonPath('message', 'Comment unhighlighted successfully')
            ->assertJsonPath('data.is_highlighted', false);

        $comment->refresh();
        $commentOwnerProfile->refresh();

        $this->assertFalse($comment->is_highlighted);
        $this->assertSame(0, (int) $commentOwnerProfile->ranking_points);
        Notification::assertSentToTimes($commentOwner, CommentHighlightedNotification::class, 1);
    }

    public function test_highlight_toggle_has_cooldown(): void
    {
        $subjectOwner = User::factory()->create();
        Profile::create(['user_id' => $subjectOwner->id, 'ranking_points' => 0]);

        $commentOwner = User::factory()->create();
        Profile::create(['user_id' => $commentOwner->id, 'ranking_points' => 0]);

        $post = Post::factory()->create([
            'user_id' => $subjectOwner->id,
            'is_published' => true,
        ]);

        $comment = Comment::create([
            'user_id' => $commentOwner->id,
            'post_id' => $post->id,
            'body' => 'test comment',
        ]);

        Sanctum::actingAs($subjectOwner);

        $this->postJson("/api/comments/{$comment->id}/highlight")
            ->assertOk();

        $this->postJson("/api/comments/{$comment->id}/highlight")
            ->assertStatus(429)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Please wait a moment before toggling highlight again.');
    }

    public function test_non_subject_owner_cannot_toggle_highlight(): void
    {
        $subjectOwner = User::factory()->create();
        Profile::create(['user_id' => $subjectOwner->id, 'ranking_points' => 0]);

        $commentOwner = User::factory()->create();
        Profile::create(['user_id' => $commentOwner->id, 'ranking_points' => 0]);

        $intruder = User::factory()->create();
        Profile::create(['user_id' => $intruder->id, 'ranking_points' => 0]);

        $post = Post::factory()->create([
            'user_id' => $subjectOwner->id,
            'is_published' => true,
        ]);

        $comment = Comment::create([
            'user_id' => $commentOwner->id,
            'post_id' => $post->id,
            'body' => 'test comment',
        ]);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/comments/{$comment->id}/highlight")
            ->assertStatus(403);

        $comment->refresh();
        $this->assertFalse((bool) $comment->is_highlighted);
    }
}
