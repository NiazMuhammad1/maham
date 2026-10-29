<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events stay on: references like REQ-482913 / BK-482913 are created in model events.
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            PageSeeder::class,
            LocationSeeder::class,
            BankSeeder::class,
            OnboardingSlideSeeder::class,
            ServiceSeeder::class,
            DemoUserSeeder::class,
            DemoMarketplaceSeeder::class,
            DemoBookingSeeder::class,
            DemoProviderDashboardSeeder::class,
        ]);
    }
}
