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
}
