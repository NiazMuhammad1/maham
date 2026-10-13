<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

        $this->seedNotifications($user);
    }

    /**
     * The four notifications from the Notifications design (the first one unread).
     */
    private function seedNotifications(User $user): void
    {
        $user->notifications()->delete();

        $notifications = [
            ['work_completed', ['provider' => 'Mohammed Al-Rashidi'], now()->subHours(3)],
            ['work_started', ['provider' => 'Mohammed Al-Rashidi'], now()->subHours(2)],
            ['payment_successful', ['service' => 'air conditioning maintenance', 'date' => 'Monday, 17th, 2026 at 10:30 AM'], now()->subHour()],
            ['offer_received', ['provider' => 'Mohammed Al-Rashidi', 'service' => 'air conditioning maintenance'], now()],
        ];

        foreach ($notifications as [$type, $params, $createdAt]) {
            $user->notifications()->create([
                'id' => Str::uuid()->toString(),
                'type' => $type,
                'data' => ['params' => $params],
                'read_at' => $type === 'offer_received' ? null : $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
