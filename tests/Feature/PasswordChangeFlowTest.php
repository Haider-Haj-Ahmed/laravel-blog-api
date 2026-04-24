<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordChangeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->postJson('/api/change-password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_change_password_updates_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        // Create existing API sessions that should be revoked after password change.
        $user->createToken('device-1');
        $user->createToken('device-2');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/change-password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Password changed successfully and you will be logged out from all devices , pleas login again.');

        $user->refresh();

        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_change_password_fails_when_current_password_is_wrong(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('errors.current_password.0', 'The provided password is incorrect.');

        $user->refresh();

        $this->assertTrue(Hash::check('old-password', $user->password));
    }
}
