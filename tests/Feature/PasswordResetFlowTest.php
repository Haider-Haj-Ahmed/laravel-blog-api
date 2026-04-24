<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_generic_success_for_existing_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'known@example.com',
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'known@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'If your email exists in our system, a password reset link has been sent.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_returns_same_generic_success_for_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'unknown@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'If your email exists in our system, a password reset link has been sent.');
    }

    public function test_reset_password_updates_password_and_revokes_api_tokens(): void
    {
        /** @var User&\Illuminate\Contracts\Auth\CanResetPassword $user */
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => Hash::make('old-password'),
        ]);

        /** @var PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($user);

        Sanctum::actingAs($user);
        $user->createToken('device-token');

        $response = $this->postJson('/api/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Password has been reset successfully.');

        $user->refresh();

        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'invalid-token@example.com',
        ]);

        $response = $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Unable to reset password');
    }
}
