<?php

namespace Tests\Feature\Api;

use App\Models\Bank;
use App\Models\District;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\BankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_profile_shows_the_header_and_saved_settings(): void
    {
        $provider = $this->approvedProvider();

        Sanctum::actingAs($provider);

        $this->getJson('/api/v1/provider/business-profile')
            ->assertOk()
            ->assertJsonPath('data.provider.id', $provider->id)
            ->assertJsonPath('data.provider.tagline', 'Professional AC and Electronic Services')
            ->assertJsonPath('data.service_ids', $provider->providedServices->pluck('id')->all())
            ->assertJsonPath('data.service_area.city.id', $provider->serviceAreas->first()->city_id)
            ->assertJsonPath('data.business_details.business_name', $provider->providerProfile->business_name)
            ->assertJsonPath('data.has_payout_account', false);
    }

    public function test_provider_updates_services_service_area_and_business_details(): void
    {
        $provider = $this->approvedProvider();
        $services = Service::factory()->count(2)->create();
        $district = District::factory()->create();

        Sanctum::actingAs($provider);

        $this->putJson('/api/v1/provider/business-profile/services', ['service_ids' => $services->pluck('id')->all()])
            ->assertOk()
            ->assertJsonPath('message', 'Changes saved.')
            ->assertJsonCount(2, 'data.service_ids');

        $this->putJson('/api/v1/provider/business-profile/service-area', [
            'city_id' => $district->city_id,
            'district_id' => $district->id,
            'postal_code' => '12345',
        ])
            ->assertOk()
            ->assertJsonPath('data.service_area.district.id', $district->id);
        $this->assertSame(1, $provider->serviceAreas()->count());

        $this->putJson('/api/v1/provider/business-profile/business-details', [
            'business_name' => 'Cool Air Co.',
            'tagline' => 'Fast AC repair',
            'skills_experience' => '10 years of HVAC work.',
            'website' => 'https://coolair.sa',
        ])
            ->assertOk()
            ->assertJsonPath('data.business_details.business_name', 'Cool Air Co.')
            ->assertJsonPath('data.provider.tagline', 'Fast AC repair');

        // An approved provider stays approved after editing (no new review)
        $this->assertSame(ProviderProfile::STATUS_APPROVED, $provider->providerProfile->fresh()->status);
    }

    public function test_provider_saves_payout_account(): void
    {
        $this->seed(BankSeeder::class);
        $alRajhi = Bank::firstWhere('swift_code', 'RJHISARI');

        Sanctum::actingAs($this->approvedProvider());

        $this->getJson('/api/v1/provider/payout-account', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.banks.1.name', 'مصرف الراجحي')
            ->assertJsonPath('data.payout_account', null);

        // IBAN typed with spaces and small letters, Swift left empty = the bank's code
        $this->putJson('/api/v1/provider/payout-account', [
            'bank_id' => $alRajhi->id,
            'account_holder_name' => 'Mohammed Al-Rashidi',
            'iban' => 'sa03 8000 0000 6080 1016 7519',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Changes saved.')
            ->assertJsonPath('data.payout_account.iban', 'SA0380000000608010167519')
            ->assertJsonPath('data.payout_account.swift_code', 'RJHISARI');

        // Saving again replaces the same account
        $this->putJson('/api/v1/provider/payout-account', [
            'bank_id' => $alRajhi->id,
            'account_holder_name' => 'Mohammed A.',
            'iban' => 'SA0380000000608010167519',
            'swift_code' => 'RJHISARIXXX',
        ])->assertJsonPath('data.payout_account.account_holder_name', 'Mohammed A.');

        $this->getJson('/api/v1/provider/business-profile')->assertJsonPath('data.has_payout_account', true);
    }

    public function test_invalid_iban_is_rejected_in_both_languages(): void
    {
        $this->seed(BankSeeder::class);

        Sanctum::actingAs($this->approvedProvider());

        $body = ['bank_id' => Bank::first()->id, 'account_holder_name' => 'Mohammed', 'iban' => 'SA12345'];

        $this->putJson('/api/v1/provider/payout-account', $body)
            ->assertJsonValidationErrors(['iban' => 'Please enter a valid Saudi IBAN']);

        $this->putJson('/api/v1/provider/payout-account', $body, ['Accept-Language' => 'ar'])
            ->assertJsonValidationErrors(['iban' => 'يرجى إدخال رقم آيبان سعودي صحيح']);
    }

    public function test_business_profile_is_only_for_approved_providers(): void
    {
        $pending = ProviderProfile::factory()->pending()->create()->user;
        $service = Service::factory()->create();

        foreach ([User::factory()->create(), $pending] as $user) {
            Sanctum::actingAs($user);

            $this->getJson('/api/v1/provider/business-profile')->assertForbidden();
            $this->getJson('/api/v1/provider/payout-account')->assertForbidden();
            $this->putJson('/api/v1/provider/business-profile/services', ['service_ids' => [$service->id]])->assertForbidden();
        }
    }

    /**
     * An approved provider with one service and a whole-city service area.
     */
    private function approvedProvider(): User
    {
        $provider = ProviderProfile::factory()->create()->user;

        $provider->providedServices()->attach(Service::factory()->create());
        $provider->serviceAreas()->create(['city_id' => District::factory()->create()->city_id]);

        return $provider->fresh();
    }
}
