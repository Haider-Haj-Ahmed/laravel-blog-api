<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileShowMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_own_profile_with_showme(): void
    {
        $user = User::factory()->create([
            'name' => 'Show Me User',
            'username' => 'showme-user',
            'email' => 'showme@example.com',
        ]);

        Profile::create([
            'user_id' => $user->id,
            'bio' => 'This is my bio',
            'website' => 'https://example.com',
            'location' => 'Damascus',
            'ranking_points' => 1200,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/showme');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.username', 'showme-user')
            ->assertJsonPath('data.email', 'showme@example.com')
            ->assertJsonPath('data.bio', 'This is my bio');
    }

    public function test_showme_requires_authentication(): void
    {
        $this->getJson('/api/showme')
            ->assertUnauthorized();
    }
}
