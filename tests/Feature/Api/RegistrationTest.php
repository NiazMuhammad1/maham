<?php

namespace Tests\Feature\Api;

use App\Mail\OtpMail;
use App\Models\District;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_registration_flow_completes_step_by_step(): void
    {
        Mail::fake();
        Storage::fake('public');
        $district = District::factory()->create();

        $this->postJson('/api/v1/auth/register/send-otp', ['phone' => '0512345678'])
            ->assertOk()
            ->assertJsonPath('data.phone', '512345678');

        $token = $this->postJson('/api/v1/auth/register/verify-otp', ['phone' => '+966512345678', 'otp' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.next_step', User::STEP_PERSONAL_DETAILS)
            ->json('data.token');

        $this->withToken($token);

        $this->postJson('/api/v1/auth/register/personal-details', [
            'name' => 'Ahmed Ali',
            'username' => 'Ahmed_Ali',
            'national_id' => '1000000008',
        ])->assertOk()->assertJsonPath('data.next_step', User::STEP_CREDENTIALS);

        $this->postJson('/api/v1/auth/register/credentials', [
            'email' => 'ahmed@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertOk()->assertJsonPath('data.next_step', User::STEP_VERIFY_EMAIL);

        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo('ahmed@example.com'));

        $this->postJson('/api/v1/auth/email/verify-otp', ['otp' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.next_step', User::STEP_ADDRESS);

        $this->postJson('/api/v1/auth/register/address', [
            'city_id' => $district->city_id,
            'district_id' => $district->id,
            'street_address' => 'King Fahd Road',
            'postal_code' => '12345',
        ])->assertOk()->assertJsonPath('data.next_step', User::STEP_PROFILE_IMAGE);

        $this->post('/api/v1/auth/register/complete', [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.next_step', User::STEP_COMPLETED)
            ->assertJsonPath('data.user.username', 'ahmed_ali')
            ->assertJsonPath('data.user.default_address.district.id', $district->id);

        $user = User::firstWhere('phone', '512345678');
        $this->assertNotNull($user->registration_completed_at);
        Storage::disk('public')->assertExists($user->getFirstMedia('avatar')->getPathRelativeToRoot());
    }

    public function test_wrong_otp_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register/send-otp', ['phone' => '0512345678'])->assertOk();

        $this->postJson('/api/v1/auth/register/verify-otp', ['phone' => '0512345678', 'otp' => '9999'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('otp');

        $this->assertDatabaseMissing('users', ['phone' => '512345678']);
    }

    public function test_otp_cannot_be_resent_before_cooldown(): void
    {
        $this->postJson('/api/v1/auth/register/send-otp', ['phone' => '0512345678'])->assertOk();

        $this->postJson('/api/v1/auth/register/send-otp', ['phone' => '0512345678'])->assertTooManyRequests();
    }

    public function test_registered_phone_cannot_register_again(): void
    {
        User::factory()->create(['phone' => '512345678', 'registration_completed_at' => now()]);

        $this->postJson('/api/v1/auth/register/send-otp', ['phone' => '0512345678'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_invalid_saudi_phone_and_national_id_are_rejected(): void
    {
        $this->postJson('/api/v1/auth/register/send-otp', ['phone' => '0412345678'])
            ->assertJsonValidationErrors('phone');

        $user = User::factory()->create(['phone' => '512345678', 'phone_verified_at' => now()]);

        $this->actingAs($user)
            ->postJson('/api/v1/auth/register/personal-details', [
                'name' => 'Ahmed Ali',
                'username' => 'ahmed',
                'national_id' => '1234567890',
            ])
            ->assertJsonValidationErrors('national_id');
    }

    public function test_registration_cannot_be_completed_before_previous_steps(): void
    {
        $user = User::factory()->create(['phone' => '512345678', 'phone_verified_at' => now()]);

        $this->actingAs($user)
            ->postJson('/api/v1/auth/register/complete')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('next_step');
    }

    public function test_validation_messages_are_returned_in_arabic(): void
    {
        $this->postJson('/api/v1/auth/register/send-otp', [], ['Accept-Language' => 'ar'])
            ->assertJsonPath('message', 'حقل رقم الجوال مطلوب.');
    }
}
