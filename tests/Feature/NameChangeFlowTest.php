<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NameChangeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_name_requires_authentication(): void
    {
        $response = $this->postJson('/api/change-name', [
            'name' => 'John Newname',
            'password' => 'old-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_change_name_updates_name_when_password_is_correct(): void
    {
        $user = User::factory()->create([
            'name' => 'John Old',
            'password' => Hash::make('old-password'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/change-name', [
            'name' => 'John Newname',
            'password' => 'old-password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Name updated successfully.')
            ->assertJsonPath('data.name', 'John Newname');

        $user->refresh();

        $this->assertSame('John Newname', $user->name);
    }

    public function test_change_name_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'name' => 'John Old',
            'password' => Hash::make('old-password'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/change-name', [
            'name' => 'John Newname',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('errors.password.0', 'The provided password is incorrect.');

        $user->refresh();

        $this->assertSame('John Old', $user->name);
    }

    public function test_change_name_fails_for_blocked_impersonation_name(): void
    {
        $user = User::factory()->create([
            'name' => 'John Old',
            'password' => Hash::make('old-password'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/change-name', [
            'name' => 'Official Support Team',
            'password' => 'old-password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('errors.name.0', 'This name is not allowed. Please choose a different display name.');

        $user->refresh();

        $this->assertSame('John Old', $user->name);
    }

    public function test_change_name_fails_while_on_cooldown(): void
    {
        $user = User::factory()->create([
            'name' => 'John Old',
            'password' => Hash::make('old-password'),
        ]);

        Cache::put("user:{$user->id}:name-change-cooldown", now()->addHours(12)->toDateTimeString(), now()->addHours(12));

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/change-name', [
            'name' => 'John Newname',
            'password' => 'old-password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Name change is on cooldown');

        $user->refresh();

        $this->assertSame('John Old', $user->name);
    }
}
