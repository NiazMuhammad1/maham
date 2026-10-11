<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\District;
use App\Models\OnboardingSlide;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_config_reports_force_update_for_old_versions(): void
    {
        $this->seed(SettingSeeder::class);

        $this->getJson('/api/v1/app/config?platform=android&version=0.9.0')
            ->assertOk()
            ->assertJsonPath('data.update', 'force')
            ->assertJsonCount(2, 'data.languages');

        $this->getJson('/api/v1/app/config?platform=android&version=1.0.0')
            ->assertJsonPath('data.update', 'optional');
    }

    public function test_onboarding_slides_are_localized_and_ordered(): void
    {
        OnboardingSlide::factory()->create(['title_en' => 'Second', 'title_ar' => 'الثانية', 'sort_order' => 2]);
        OnboardingSlide::factory()->create(['title_en' => 'First', 'title_ar' => 'الأولى', 'sort_order' => 1]);
        OnboardingSlide::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/onboarding-slides', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'الأولى');
    }

    public function test_cities_and_districts_list_only_active_records(): void
    {
        $city = City::factory()->create();
        City::factory()->inactive()->create();
        District::factory()->count(3)->for($city)->create();

        $this->getJson('/api/v1/cities')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/cities/{$city->id}/districts")->assertOk()->assertJsonCount(3, 'data');
    }
}
