<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostSplitUpdateEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_endpoint_updates_text_without_touching_existing_photos(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Old title',
            'body' => 'Old body',
        ]);

        $existingPath = 'post_photos/existing-photo.jpg';
        Storage::disk('public')->put($existingPath, 'old-photo');
        $existingPhoto = PostPhoto::create([
            'post_id' => $post->id,
            'path' => $existingPath,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/posts/{$post->id}/content", [
            'title' => 'Updated title',
            'body' => 'Updated body',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Post content updated successfully');

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated title',
            'body' => 'Updated body',
        ]);

        $this->assertDatabaseHas('post_photos', [
            'id' => $existingPhoto->id,
            'post_id' => $post->id,
            'path' => $existingPath,
        ]);

        Storage::disk('public')->assertExists($existingPath);
    }

    public function test_photo_endpoints_allow_incremental_add_replace_and_delete(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Title stays',
            'body' => 'Body stays',
        ]);

        $firstPath = 'post_photos/first-photo.jpg';
        Storage::disk('public')->put($firstPath, 'first-photo');
        $firstPhoto = PostPhoto::create([
            'post_id' => $post->id,
            'path' => $firstPath,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($user);

        $addResponse = $this->post("/api/posts/{$post->id}/photos", [
            'photo' => UploadedFile::fake()->image('second.jpg'),
        ]);
        $addResponse->assertOk();
        $addResponse->assertJsonPath('message', 'Post photo added successfully');

        $this->assertDatabaseCount('post_photos', 2);

        $replaceResponse = $this->post("/api/posts/{$post->id}/photos/{$firstPhoto->id}", [
            '_method' => 'PUT',
            'photo' => UploadedFile::fake()->image('first-replaced.jpg'),
        ]);
        $replaceResponse->assertOk();
        $replaceResponse->assertJsonPath('message', 'Post photo replaced successfully');
        Storage::disk('public')->assertMissing($firstPath);

        $photoToDeleteId = PostPhoto::where('post_id', $post->id)
            ->where('id', '!=', $firstPhoto->id)
            ->value('id');
        $this->assertNotNull($photoToDeleteId);

        $deleteResponse = $this->deleteJson("/api/posts/{$post->id}/photos/{$photoToDeleteId}");
        $deleteResponse->assertOk();
        $deleteResponse->assertJsonPath('message', 'Post photo deleted successfully');

        $post->refresh();
        $this->assertSame('Title stays', $post->title);
        $this->assertSame('Body stays', $post->body);

        $remainingPhotos = PostPhoto::where('post_id', $post->id)
            ->orderBy('sort_order')
            ->get();
        $this->assertCount(1, $remainingPhotos);
        $this->assertSame(0, (int) $remainingPhotos->first()->sort_order);
    }

    public function test_legacy_combined_update_endpoint_is_not_available_anymore(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/posts/{$post->id}", [
            'title' => 'Should not update',
        ]);

        $response->assertStatus(404);
    }
}
