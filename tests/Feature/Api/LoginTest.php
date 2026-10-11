<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_email_and_password(): void
    {
        $user = User::factory()->create(['email' => 'ahmed@example.com', 'password' => 'Secret123']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'Ahmed@Example.com',
            'password' => 'Secret123',
            'fcm_token' => 'fcm-123',
            'platform' => 'android',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'next_step', 'user']]);

        $this->assertDatabaseHas('device_tokens', ['user_id' => $user->id, 'token' => 'fcm-123']);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'ahmed@example.com', 'password' => 'Secret123']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ahmed@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->create(['email' => 'ahmed@example.com', 'password' => 'Secret123']);
        $user->forceFill(['status' => 'suspended'])->save();

        $this->postJson('/api/v1/auth/login', ['email' => 'ahmed@example.com', 'password' => 'Secret123'])
            ->assertForbidden();
    }

    public function test_guest_login_returns_guest_token(): void
    {
        $this->postJson('/api/v1/auth/guest')
            ->assertOk()
            ->assertJsonPath('data.next_step', User::STEP_GUEST)
            ->assertJsonPath('data.user.is_guest', true);
    }

    public function test_forgot_password_flow_resets_password(): void
    {
        $user = User::factory()->create(['phone' => '512345678', 'phone_verified_at' => now()]);
        $user->createToken('old-device');

        $this->postJson('/api/v1/auth/forgot-password/send-otp', ['phone' => '0512345678'])->assertOk();

        $resetToken = $this->postJson('/api/v1/auth/forgot-password/verify-otp', ['phone' => '0512345678', 'otp' => '1234'])
            ->assertOk()
            ->json('data.reset_token');

        $this->postJson('/api/v1/auth/forgot-password/reset', [
            'reset_token' => $resetToken,
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewSecret123', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());

        $this->postJson('/api/v1/auth/forgot-password/reset', [
            'reset_token' => $resetToken,
            'password' => 'Another123',
            'password_confirmation' => 'Another123',
        ])->assertJsonValidationErrors('reset_token');
    }

    public function test_change_password_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'wrong',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertJsonValidationErrors('current_password');

        $this->withToken($token)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Secret123',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertOk();
    }

    public function test_google_login_creates_account_that_must_verify_phone(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-google-client',
            'sub' => 'google-user-1',
            'email' => 'ahmed@gmail.com',
            'email_verified' => 'true',
            'name' => 'Ahmed Ali',
        ])]);

        $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'id_token' => 'fake-token'])
            ->assertOk()
            ->assertJsonPath('data.next_step', User::STEP_VERIFY_PHONE)
            ->assertJsonPath('data.user.email_verified', true);

        $this->assertDatabaseHas('social_accounts', ['provider' => 'google', 'provider_user_id' => 'google-user-1']);
    }

    public function test_google_token_for_another_app_is_rejected(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'iss' => 'https://accounts.google.com',
            'aud' => 'someone-elses-app',
            'sub' => 'google-user-1',
        ])]);

        $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'id_token' => 'fake-token'])
            ->assertJsonValidationErrors('id_token');
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }
}
