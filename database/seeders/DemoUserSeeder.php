<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * A fully registered customer for testing login (not seeded in production).
     *
     * Email: demo@maham.sa / Password: Demo1234 / Phone: 0500000001
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $user = User::updateOrCreate(['email' => 'demo@maham.sa'], [
            'name' => 'Demo Customer',
            'username' => 'demo_customer',
            'password' => 'Demo1234',
            'phone_country_code' => '+966',
            'phone' => '500000001',
            'national_id' => '1000000008',
            'language' => 'en',
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'registration_completed_at' => now(),
        ])->save();

        $city = City::with('districts')->firstWhere('name_en', 'Riyadh');

        if ($city) {
            $user->addresses()->updateOrCreate(['is_default' => true], [
                'label' => 'Home',
                'city_id' => $city->id,
                'district_id' => $city->districts->first()?->id,
                'street_address' => 'King Fahd Road, Building 12',
                'postal_code' => '12345',
            ]);
        }
    }
}
