<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_block_and_unblock_and_list_blocked(): void
    {
        $alice = User::factory()->create(['username' => 'alice_block']);
        $bob = User::factory()->create(['username' => 'bob_block']);
        Profile::create(['user_id' => $alice->id, 'ranking_points' => 0]);
        Profile::create(['user_id' => $bob->id, 'ranking_points' => 0]);

        Sanctum::actingAs($alice);

        $this->postJson('/api/users/bob_block/block')->assertOk();
        $this->getJson('/api/blocks')->assertOk()->assertJsonFragment(['username' => 'bob_block']);

        $this->deleteJson('/api/users/bob_block/block')->assertOk();
        $response = $this->getJson('/api/blocks')->assertOk();
        $usernames = collect($response->json('data'))->pluck('username')->all();
        $this->assertNotContains('bob_block', $usernames);
    }

    public function test_blocked_author_posts_hidden_from_authenticated_index(): void
    {
        $alice = User::factory()->create(['username' => 'alice_idx']);
        $bob = User::factory()->create(['username' => 'bob_idx']);
        Profile::create(['user_id' => $alice->id, 'ranking_points' => 0]);
        Profile::create(['user_id' => $bob->id, 'ranking_points' => 0]);

        $post = Post::factory()->create([
            'user_id' => $bob->id,
            'is_published' => true,
            'title' => 'HiddenFromAliceTitle',
        ]);

        Sanctum::actingAs($alice);
        $this->postJson('/api/users/bob_idx/block')->assertOk();

        $response = $this->getJson('/api/posts')->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($post->id, $ids);
    }

    public function test_blocking_user_removes_follow_relationships_in_both_directions(): void
    {
        $alice = User::factory()->create(['username' => 'alice_follow_block']);
        $bob = User::factory()->create(['username' => 'bob_follow_block']);
        Profile::create(['user_id' => $alice->id, 'ranking_points' => 0]);
        Profile::create(['user_id' => $bob->id, 'ranking_points' => 0]);

        Sanctum::actingAs($alice);
        $this->postJson('/api/users/bob_follow_block/follow')->assertOk();

        Sanctum::actingAs($bob);
        $this->postJson('/api/users/alice_follow_block/follow')->assertOk();

        $this->assertDatabaseHas('follows', [
            'follower_id' => $alice->id,
            'followed_id' => $bob->id,
        ]);
        $this->assertDatabaseHas('follows', [
            'follower_id' => $bob->id,
            'followed_id' => $alice->id,
        ]);

        Sanctum::actingAs($alice);
        $this->postJson('/api/users/bob_follow_block/block')->assertOk();

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $alice->id,
            'followed_id' => $bob->id,
        ]);
        $this->assertDatabaseMissing('follows', [
            'follower_id' => $bob->id,
            'followed_id' => $alice->id,
        ]);

        $alice->refresh();
        $bob->refresh();

        $this->assertSame(0, $alice->following_count);
        $this->assertSame(0, $alice->followers_count);
        $this->assertSame(0, $bob->following_count);
        $this->assertSame(0, $bob->followers_count);
    }
}
