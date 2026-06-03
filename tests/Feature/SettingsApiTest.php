<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Services\UserSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_settings_returns_defaults_when_profile_just_created(): void
    {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id, 'ranking_points' => 0]);

        Sanctum::actingAs($user);

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.settings', UserSettingsService::DEFAULTS);
    }

    public function test_patch_settings_merges_theme(): void
    {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id, 'ranking_points' => 0]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/settings', ['theme' => 'dark'])
            ->assertOk()
            ->assertJsonPath('data.settings.theme', 'dark');

        $this->assertSame('dark', Profile::where('user_id', $user->id)->first()->settings['theme']);
    }

    public function test_patch_settings_merges_nested_notification_event(): void
    {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id, 'ranking_points' => 0]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/settings', [
            'notifications' => ['events' => ['likes' => false]],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.notifications.events.likes', false);

        $profile = Profile::where('user_id', $user->id)->first();
        $this->assertFalse($profile->settings['notifications']['events']['likes']);
    }

    public function test_patch_settings_merges_privacy_flag(): void
    {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id, 'ranking_points' => 0]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/settings', [
            'privacy' => ['show_email' => true],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.privacy.show_email', true);
    }

    public function test_patch_settings_preserves_other_keys_when_partially_updating(): void
    {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id, 'ranking_points' => 0]);

        Sanctum::actingAs($user);

        // Only update theme — all other defaults should remain intact
        $this->patchJson('/api/settings', ['theme' => 'light'])
            ->assertOk()
            ->assertJsonPath('data.settings.theme', 'light')
            ->assertJsonPath('data.settings.notifications.events.likes', true)
            ->assertJsonPath('data.settings.privacy.profile_discoverable', true);
    }

    public function test_patch_settings_rejects_unknown_keys(): void
    {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id, 'ranking_points' => 0]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/settings', ['evil_key' => 'x'])
            ->assertStatus(422);
    }

    public function test_patch_settings_rejects_invalid_theme_value(): void
    {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id, 'ranking_points' => 0]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/settings', ['theme' => 'banana'])
            ->assertStatus(422)
            ->assertJsonPath('errors.theme.0', 'The selected theme is invalid.');
    }

    public function test_patch_settings_does_not_append_unknown_key_errors_when_core_validation_fails(): void
    {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id, 'ranking_points' => 0]);

        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/settings', [
            'theme' => ['foo' => 'dark'],
            'evil_key' => 'x',
        ])->assertStatus(422);

        $response->assertJsonMissingPath('errors.evil_key');
        $response->assertJsonPath('errors.theme.0', 'The theme field must be a string.');
    }

    public function test_unauthenticated_user_cannot_get_settings(): void
    {
        $this->getJson('/api/settings')->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_update_settings(): void
    {
        $this->patchJson('/api/settings', ['theme' => 'dark'])->assertUnauthorized();
    }
}