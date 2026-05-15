<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_report_another_users_post(): void
    {
        $reporter = User::factory()->create();
        $author = User::factory()->create();
        Profile::create(['user_id' => $reporter->id, 'ranking_points' => 0]);
        Profile::create(['user_id' => $author->id, 'ranking_points' => 0]);

        $post = Post::factory()->create([
            'user_id' => $author->id,
            'is_published' => true,
        ]);

        Sanctum::actingAs($reporter);

        $this->postJson('/api/reports', [
            'kind' => 'post',
            'id' => $post->id,
            'reason' => 'spam',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', Report::STATUS_PENDING);

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'reportable_id' => $post->id,
            'reportable_type' => 'post',
            'reason' => 'spam',
            'status' => Report::STATUS_PENDING,
        ]);
    }

    public function test_user_cannot_report_own_post(): void
    {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id, 'ranking_points' => 0]);
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/reports', [
            'kind' => 'post',
            'id' => $post->id,
            'reason' => 'spam',
        ])->assertStatus(422);
    }
}
