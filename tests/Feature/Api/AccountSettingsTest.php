<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\District;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $user = User::factory()->create([
            'username' => 'abdul.1523',
            'phone' => '569854219',
            'national_id' => '1000000008',
            'password' => 'Secret123',
        ]);
        $user->forceFill(['phone_verified_at' => now(), 'registration_completed_at' => now()])->save();

        return $user->fresh();
    }

    public function test_user_can_view_account_settings(): void
    {
        Sanctum::actingAs($this->customer());

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.user.username', 'abdul.1523')
            ->assertJsonPath('data.user.push_notifications', true)
            ->assertJsonPath('data.user.email_notifications', true);
    }

    public function test_user_can_update_personal_details(): void
    {
        $user = $this->customer();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile/personal-details', [
            'name' => 'Abdul Rehman',
            'username' => 'Abdul.Rehman',
            'national_id' => '1092319571',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.username', 'abdul.rehman');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Abdul Rehman', 'national_id' => '1092319571']);
    }

    public function test_username_used_by_another_user_is_rejected(): void
    {
        User::factory()->create(['username' => 'taken_name']);
        Sanctum::actingAs($this->customer());

        $this->putJson('/api/v1/profile/personal-details', [
            'name' => 'Abdul Rehman',
            'username' => 'taken_name',
            'national_id' => '1000000008',
        ])->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    public function test_user_can_update_home_address(): void
    {
        $user = $this->customer();
        $city = City::create(['name_en' => 'Riyadh', 'name_ar' => 'الرياض', 'is_active' => true]);
        $district = District::create(['city_id' => $city->id, 'name_en' => 'Al Malqa', 'name_ar' => 'الملقا', 'is_active' => true]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile/address', [
            'city_id' => $city->id,
            'district_id' => $district->id,
            'street_address' => '675 King Jamil Rd.',
            'postal_code' => '23142',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.default_address.street_address', '675 King Jamil Rd.');

        $this->assertSame(1, $user->addresses()->count());
    }

    public function test_user_can_toggle_notification_settings(): void
    {
        $user = $this->customer();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile/notifications', ['email_notifications' => false])
            ->assertOk()
            ->assertJsonPath('data.push_notifications', true)
            ->assertJsonPath('data.email_notifications', false);

        $this->putJson('/api/v1/profile/notifications', [])->assertUnprocessable();
    }

    public function test_update_password_requires_otp_sent_to_phone(): void
    {
        $user = $this->customer();
        $user->createToken('old-device');
        $currentToken = $user->createToken('this-device')->plainTextToken;

        $passwordFields = [
            'current_password' => 'Secret123',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ];

        $this->withToken($currentToken)
            ->postJson('/api/v1/profile/password/send-otp', $passwordFields)
            ->assertOk()
            ->assertJsonPath('data.phone', '569854219');

        $this->assertDatabaseHas('otp_codes', ['identifier' => '569854219', 'purpose' => 'update_password']);

        $this->withToken($currentToken)
            ->postJson('/api/v1/profile/password', [...$passwordFields, 'otp' => '9999'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('otp');

        $this->withToken($currentToken)
            ->postJson('/api/v1/profile/password', [...$passwordFields, 'otp' => '1234'])
            ->assertOk();

        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_wrong_current_password_does_not_send_otp(): void
    {
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/v1/profile/password/send-otp', [
            'current_password' => 'wrong-pass1',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_user_can_update_phone_number_with_otp(): void
    {
        $user = $this->customer();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/phone/send-otp', ['phone' => '0535884520'])
            ->assertOk()
            ->assertJsonPath('data.phone', '535884520');

        $this->postJson('/api/v1/profile/phone/verify-otp', ['phone' => '0535884520', 'otp' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.user.phone', '535884520');

        $this->assertSame('535884520', $user->fresh()->phone);
    }

    public function test_phone_used_by_another_account_or_same_phone_is_rejected(): void
    {
        User::factory()->create(['phone' => '535884520']);
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/v1/profile/phone/send-otp', ['phone' => '0535884520'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->postJson('/api/v1/profile/phone/send-otp', ['phone' => '0569854219'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_guest_cannot_change_account_settings(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_guest' => true]));

        $this->putJson('/api/v1/profile/notifications', ['push_notifications' => false])->assertForbidden();
        $this->postJson('/api/v1/profile/phone/send-otp', ['phone' => '0535884520'])->assertUnprocessable();
    }
}
