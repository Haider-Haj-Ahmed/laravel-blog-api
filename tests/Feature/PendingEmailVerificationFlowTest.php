<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use App\Notifications\OtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PendingEmailVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_email_sets_pending_email_and_keeps_current_email_until_verification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $user->createToken('test-token');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/updateemail', [
            'email' => 'new@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('status', 'success');

        $user->refresh();

        $this->assertSame('old@example.com', $user->email);
        $this->assertSame('new@example.com', $user->pending_email);
        $this->assertDatabaseHas('otps', [
            'user_id' => $user->id,
            'channel' => 'email',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        Notification::assertSentOnDemand(
            OtpNotification::class,
            function (OtpNotification $notification, array $channels, object $notifiable): bool {
                return $notifiable->routeNotificationFor('mail') === 'new@example.com';
            }
        );
    }

    public function test_otp_verify_swaps_pending_email_into_primary_email(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'email_verified_at' => null,
        ]);

        Otp::create([
            'user_id' => $user->id,
            'code' => Hash::make('123456'),
            'channel' => 'email',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/otp/verify', [
            'email' => 'new@example.com',
            'code' => '123456',
        ]);

        $response->assertOk()->assertJsonPath('status', 'success');

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseMissing('otps', [
            'user_id' => $user->id,
        ]);
    }

    public function test_otp_resend_works_for_pending_email_even_if_account_already_verified(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'email_verified_at' => now(),
        ]);

        Otp::create([
            'user_id' => $user->id,
            'code' => Hash::make('111111'),
            'channel' => 'email',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/otp/resend', [
            'email' => 'new@example.com',
        ]);

        $response->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('otps', [
            'user_id' => $user->id,
            'channel' => 'email',
        ]);

        Notification::assertSentOnDemand(
            OtpNotification::class,
            function (OtpNotification $notification, array $channels, object $notifiable): bool {
                return $notifiable->routeNotificationFor('mail') === 'new@example.com';
            }
        );
    }
}
