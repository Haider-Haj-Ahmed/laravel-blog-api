<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentIsLikedByUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_resource_reports_is_liked_by_user_correctly(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'is_published' => true,
        ]);

        $comment = Comment::create([
            'user_id' => $author->id,
            'post_id' => $post->id,
            'body' => 'Helpful comment',
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson("/api/comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('data.is_liked_by_user', false);

        $this->postJson("/api/comments/{$comment->id}/like")
            ->assertOk()
            ->assertJsonPath('data.is_liked_by_user', true);

        $this->getJson("/api/comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('data.is_liked_by_user', true);

        $this->postJson("/api/comments/{$comment->id}/like")
            ->assertOk()
            ->assertJsonPath('data.is_liked_by_user', false);

        $this->getJson("/api/comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('data.is_liked_by_user', false);
    }
}
