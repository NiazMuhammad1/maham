<?php

namespace Tests\Feature\Api;

use App\Models\District;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_applies_step_by_step_and_submits_with_a_logo(): void
    {
        Storage::fake('public');
        $user = $this->registeredUser();
        $services = Service::factory()->count(2)->create();
        $district = District::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/provider/application')
            ->assertOk()
            ->assertJsonPath('data.status', User::APPLICATION_NOT_STARTED)
            ->assertJsonPath('data.next_step', User::APPLICATION_STEP_SERVICES);

        $this->postJson('/api/v1/provider/application/services', ['service_ids' => $services->pluck('id')->all()])
            ->assertOk()
            ->assertJsonPath('data.status', User::APPLICATION_DRAFT)
            ->assertJsonPath('data.next_step', User::APPLICATION_STEP_SERVICE_AREA)
            ->assertJsonCount(2, 'data.services');

        $this->postJson('/api/v1/provider/application/service-area', [
            'city_id' => $district->city_id,
            'district_id' => $district->id,
            'postal_code' => '12345',
        ])
            ->assertOk()
            ->assertJsonPath('data.next_step', User::APPLICATION_STEP_BUSINESS_DETAILS)
            ->assertJsonPath('data.service_area.district.id', $district->id);

        $this->postJson('/api/v1/provider/application/business-details', [
            'business_name' => 'Cool Air Services',
            'tagline' => 'Professional AC and Electronic Services',
            'skills_experience' => '10 years of AC installation and repair.',
            'website' => 'https://coolair.sa',
        ])
            ->assertOk()
            ->assertJsonPath('data.next_step', User::APPLICATION_STEP_LOGO);

        $this->post('/api/v1/provider/application/submit', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'Application Submitted!')
            ->assertJsonPath('data.status', ProviderProfile::STATUS_PENDING)
            ->assertJsonPath('data.next_step', User::APPLICATION_STEP_SUBMITTED)
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.business_name', 'Cool Air Services');

        $profile = $user->providerProfile()->first();
        $this->assertNotNull($profile->submitted_at);
        $this->assertNotNull($profile->logoUrl());
        $this->assertFalse($user->fresh()->is_provider, 'The user becomes a provider only after admin approval.');
    }

    public function test_submit_without_logo_skips_the_logo_step(): void
    {
        $user = $this->userWithFilledApplication();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/provider/application/submit')
            ->assertOk()
            ->assertJsonPath('data.status', ProviderProfile::STATUS_PENDING)
            ->assertJsonPath('data.logo_url', null);
    }

    public function test_submit_requires_the_previous_steps(): void
    {
        $user = $this->registeredUser();
        $user->providedServices()->attach(Service::factory()->create());

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/provider/application/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('next_step');
    }

    public function test_application_cannot_be_changed_while_under_review(): void
    {
        $user = $this->userWithFilledApplication();
        $user->providerProfile->forceFill(['submitted_at' => now()])->save();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/provider/application/services', ['service_ids' => [Service::factory()->create()->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('application');
    }

    public function test_rejected_application_can_be_fixed_and_submitted_again(): void
    {
        $user = $this->userWithFilledApplication();
        $user->providerProfile->forceFill([
            'status' => ProviderProfile::STATUS_REJECTED,
            'rejection_reason' => 'Please add a clearer business description.',
            'submitted_at' => now()->subDay(),
        ])->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/provider/application')
            ->assertJsonPath('data.status', ProviderProfile::STATUS_REJECTED)
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.rejection_reason', 'Please add a clearer business description.');

        $this->postJson('/api/v1/provider/application/submit')
            ->assertOk()
            ->assertJsonPath('data.status', ProviderProfile::STATUS_PENDING)
            ->assertJsonPath('data.rejection_reason', null);
    }

    public function test_approved_provider_sees_approved_status(): void
    {
        $profile = ProviderProfile::factory()->create();

        Sanctum::actingAs($profile->user);

        $this->getJson('/api/v1/provider/application')
            ->assertOk()
            ->assertJsonPath('data.status', ProviderProfile::STATUS_APPROVED)
            ->assertJsonPath('data.can_edit', false);
    }

    public function test_steps_are_validated(): void
    {
        $district = District::factory()->create();
        $otherCityDistrict = District::factory()->create();

        Sanctum::actingAs($this->registeredUser());

        $this->postJson('/api/v1/provider/application/services', ['service_ids' => []])
            ->assertJsonValidationErrors('service_ids');

        $this->postJson('/api/v1/provider/application/service-area', [
            'city_id' => $district->city_id,
            'district_id' => $otherCityDistrict->id,
            'postal_code' => '12',
        ])->assertJsonValidationErrors(['district_id', 'postal_code']);

        $this->postJson('/api/v1/provider/application/business-details', ['website' => 'not-a-url'])
            ->assertJsonValidationErrors(['business_name', 'tagline', 'skills_experience', 'website']);
    }

    public function test_guests_cannot_apply(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_guest' => true, 'registration_completed_at' => now()]));

        $this->postJson('/api/v1/provider/application/services', ['service_ids' => [Service::factory()->create()->id]])
            ->assertForbidden();
    }

    /**
     * A customer who finished sign-up (only they can apply).
     */
    private function registeredUser(): User
    {
        return User::factory()->create(['registration_completed_at' => now()]);
    }

    /**
     * A user who finished steps 1-3 and only has to tap "Submit details" / "Skip".
     */
    private function userWithFilledApplication(): User
    {
        $user = $this->registeredUser();
        $district = District::factory()->create();

        $user->providedServices()->attach(Service::factory()->create());
        $user->serviceAreas()->create(['city_id' => $district->city_id, 'district_id' => $district->id]);
        ProviderProfile::factory()->pending()->for($user)->create();

        return $user->fresh();
    }
}
