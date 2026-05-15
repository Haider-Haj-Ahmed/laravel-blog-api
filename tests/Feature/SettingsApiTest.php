<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_settings_returns_empty_object_when_none_stored(): void
    {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id, 'ranking_points' => 0]);

        Sanctum::actingAs($user);

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.settings', []);
    }

    public function test_patch_settings_merges_known_keys(): void
    {
        $user = User::factory()->create();
        Profile::create([
            'user_id' => $user->id,
            'ranking_points' => 0,
            'settings' => ['theme' => 'light'],
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/settings', [
            'theme' => 'dark',
            'notify_likes' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.theme', 'dark')
            ->assertJsonPath('data.settings.notify_likes', true);

        $profile = Profile::where('user_id', $user->id)->first();
        $this->assertSame('dark', $profile->settings['theme']);
        $this->assertTrue($profile->settings['notify_likes']);
    }

    public function test_patch_settings_rejects_unknown_keys(): void
    {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id, 'ranking_points' => 0]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/settings', [
            'evil_key' => 'x',
        ])->assertStatus(422);
    }
}
