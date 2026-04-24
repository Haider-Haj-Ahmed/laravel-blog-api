<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use App\Models\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IsViewedResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_resource_returns_true_when_viewer_has_viewed_post(): void
    {
        $author = User::factory()->create();
        Profile::create(['user_id' => $author->id]);

        $viewer = User::factory()->create();
        Profile::create(['user_id' => $viewer->id]);

        $post = Post::factory()->create([
            'user_id' => $author->id,
            'is_published' => true,
        ]);

        View::create([
            'user_id' => $viewer->id,
            'viewable_type' => (new Post())->getMorphClass(),
            'viewable_id' => $post->id,
        ]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson("/api/posts/{$post->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.is_viewed', true);
    }

    public function test_blog_resource_returns_true_when_viewer_has_viewed_blog(): void
    {
        $author = User::factory()->create();
        Profile::create(['user_id' => $author->id]);

        $viewer = User::factory()->create();
        Profile::create(['user_id' => $viewer->id]);

        $blog = Blog::create([
            'user_id' => $author->id,
            'title' => 'A blog title',
            'subtitle' => 'A blog subtitle',
            'is_published' => true,
        ]);

        View::create([
            'user_id' => $viewer->id,
            'viewable_type' => (new Blog())->getMorphClass(),
            'viewable_id' => $blog->id,
        ]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson("/api/blogs/{$blog->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.is_viewed', true);
    }

    public function test_profile_resource_returns_true_when_viewer_has_viewed_profile(): void
    {
        $owner = User::factory()->create([
            'username' => 'profile-owner',
        ]);
        $ownerProfile = Profile::create(['user_id' => $owner->id]);

        $viewer = User::factory()->create();
        Profile::create(['user_id' => $viewer->id]);

        View::create([
            'user_id' => $viewer->id,
            'viewable_type' => (new Profile())->getMorphClass(),
            'viewable_id' => $ownerProfile->id,
        ]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/users/profile-owner/profile');

        $response
            ->assertOk()
            ->assertJsonPath('data.is_viewed', true);
    }

    public function test_resources_return_false_for_guest_viewer(): void
    {
        $author = User::factory()->create([
            'username' => 'guest-author',
        ]);
        $authorProfile = Profile::create(['user_id' => $author->id]);

        $post = Post::factory()->create([
            'user_id' => $author->id,
            'is_published' => true,
        ]);

        $blog = Blog::create([
            'user_id' => $author->id,
            'title' => 'Guest blog title',
            'subtitle' => 'Guest blog subtitle',
            'is_published' => true,
        ]);

        $this->getJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.is_viewed', false);

        $this->getJson("/api/blogs/{$blog->id}")
            ->assertOk()
            ->assertJsonPath('data.is_viewed', false);

        $this->getJson('/api/users/guest-author/profile')
            ->assertOk()
            ->assertJsonPath('data.is_viewed', false);

        $this->assertNotNull($authorProfile->id);
    }
}
