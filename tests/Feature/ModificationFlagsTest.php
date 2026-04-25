<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\HighlightedCommentUpdatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModificationFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_update_sets_is_modified_but_tag_only_changes_do_not(): void
    {
        $user = User::factory()->create();
        $tag = Tag::create(['name' => 'php']);

        $post = $user->posts()->create([
            'title' => 'Old title',
            'body' => 'Old body',
            'code' => null,
            'code_language' => null,
            'is_published' => false,
        ]);

        Sanctum::actingAs($user);

        $titleResponse = $this->putJson("/api/posts/{$post->id}/content", [
            'title' => 'New title',
        ]);

        $titleResponse
            ->assertOk()
            ->assertJsonPath('data.is_modified', true);

        $post->refresh();
        $this->assertTrue($post->is_modified);

        $post->forceFill(['is_modified' => false])->save();

        $tagOnlyResponse = $this->putJson("/api/posts/{$post->id}/content", [
            'tags' => [$tag->id],
        ]);

        $tagOnlyResponse
            ->assertOk()
            ->assertJsonPath('data.is_modified', false);

        $post->refresh();
        $this->assertFalse($post->is_modified);
    }

    public function test_blog_update_sets_is_modified(): void
    {
        $user = User::factory()->create();

        $blog = Blog::create([
            'user_id' => $user->id,
            'title' => 'Old blog title',
            'subtitle' => 'Old subtitle',
            'reading_time' => '4 min',
            'is_published' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/blogs/{$blog->id}", [
            'subtitle' => 'New subtitle',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.is_modified', true);

        $blog->refresh();
        $this->assertTrue($blog->is_modified);
    }

    public function test_blog_update_syncs_tags_and_tag_only_changes_do_not_set_is_modified(): void
    {
        $user = User::factory()->create();
        $oldTag = Tag::create(['name' => 'laravel']);
        $newTag = Tag::create(['name' => 'php']);

        $blog = Blog::create([
            'user_id' => $user->id,
            'title' => 'Blog title',
            'subtitle' => 'Blog subtitle',
            'reading_time' => '4 min',
            'is_published' => false,
            'is_modified' => false,
        ]);
        $blog->tags()->attach($oldTag->id);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/blogs/{$blog->id}", [
            'tags' => [$newTag->id],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.is_modified', false);

        $blog->refresh();
        $this->assertFalse($blog->is_modified);
        $this->assertDatabaseMissing('blog_tag', [
            'blog_id' => $blog->id,
            'tag_id' => $oldTag->id,
        ]);
        $this->assertDatabaseHas('blog_tag', [
            'blog_id' => $blog->id,
            'tag_id' => $newTag->id,
        ]);
    }

    public function test_comment_update_sets_is_modified_and_notifies_subject_owner_when_highlighted(): void
    {
        Notification::fake();

        $subjectOwner = User::factory()->create();
        $commentOwner = User::factory()->create();

        $post = $subjectOwner->posts()->create([
            'title' => 'Post title',
            'body' => 'Post body',
            'code' => null,
            'code_language' => null,
            'is_published' => true,
        ]);

        $comment = Comment::create([
            'user_id' => $commentOwner->id,
            'post_id' => $post->id,
            'body' => 'Original comment body',
            'code' => null,
            'code_language' => null,
        ]);

        Sanctum::actingAs($subjectOwner);
        $this->postJson("/api/comments/{$comment->id}/highlight")->assertOk();

        Sanctum::actingAs($commentOwner);
        $response = $this->postJson("/api/comments/{$comment->id}", [
            'body' => 'Edited comment body',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.is_modified', true)
            ->assertJsonPath('data.is_highlighted', true);

        $comment->refresh();
        $this->assertTrue($comment->is_modified);

        Notification::assertSentTo($subjectOwner, HighlightedCommentUpdatedNotification::class);
    }

    public function test_blog_comment_update_sets_is_modified_and_notifies_subject_owner_when_highlighted(): void
    {
        Notification::fake();

        $subjectOwner = User::factory()->create();
        $commentOwner = User::factory()->create();

        $blog = Blog::create([
            'user_id' => $subjectOwner->id,
            'title' => 'Blog title',
            'subtitle' => 'Blog subtitle',
            'reading_time' => '3 min',
            'is_published' => true,
        ]);

        $comment = Comment::create([
            'user_id' => $commentOwner->id,
            'blog_id' => $blog->id,
            'body' => 'Original blog comment body',
            'code' => null,
            'code_language' => null,
        ]);

        Sanctum::actingAs($subjectOwner);
        $this->postJson("/api/comments/{$comment->id}/highlight")->assertOk();

        Sanctum::actingAs($commentOwner);
        $response = $this->postJson("/api/comments/{$comment->id}", [
            'code' => 'echo 1;',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.is_modified', true)
            ->assertJsonPath('data.is_highlighted', true);

        $comment->refresh();
        $this->assertTrue($comment->is_modified);

        Notification::assertSentTo($subjectOwner, HighlightedCommentUpdatedNotification::class);
    }
}
