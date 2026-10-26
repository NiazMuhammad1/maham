<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\City;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoProviderDashboardSeeder extends Seeder
{
    /**
     * Demo data for the provider Home, Earnings and provider Notifications (not seeded in production).
     *
     * For the demo providers (Mohammed Al-Rashidi abdul.1523 and Khalid Al-Otaibi): a service area (whole Riyadh).
     * For Mohammed:
     * - 3 open requests from other customers ("Recent Requests")
     * - 2 more confirmed bookings ("Upcoming Bookings", together with the one from DemoMarketplaceSeeder)
     * - the 4 notifications from the provider Notifications design
     *
     * Runs after DemoBookingSeeder, and only once (skipped when Mohammed already has provider notifications).
     */
    public function run(): void
    {
        $mohammed = User::firstWhere('username', 'abdul.1523');
        $khalid = User::firstWhere('username', 'khalid.cool');
        $city = City::with('districts')->firstWhere('name_en', 'Riyadh');
        $acRepair = Service::firstWhere('slug', 'ac-repair');
        $electronics = Service::firstWhere('slug', 'electronics');

        if (app()->isProduction() || ! $mohammed || ! $khalid || ! $city || ! $acRepair || ! $electronics
            || $mohammed->notificationsFor(User::MODE_PROVIDER)->exists()) {
            return;
        }

        // "Choose Service Area": the whole city of Riyadh
        foreach ([$mohammed, $khalid] as $provider) {
            $provider->serviceAreas()->firstOrCreate(['city_id' => $city->id]);
        }

        $address = [
            'city_id' => $city->id,
            'district_id' => $city->districts->first()?->id,
            'street_address' => '675 King Jamil Rd., 213 Alamal Dist.',
            'postal_code' => '23142',
        ];

        $customers = collect(['Abdul Rehman', 'Sara Al-Qahtani', 'Fahad Al-Harbi'])
            ->map(fn (string $name) => User::factory()->create(['name' => $name, 'registration_completed_at' => now()]));

        // Recent Requests: open requests in Mohammed's services and area, no offer from him yet
        foreach ([[$acRepair, 4], [$electronics, 5], [$acRepair, 8]] as $index => [$service, $daysAhead]) {
            ServiceRequest::factory()->for($customers[$index], 'customer')->for($service)->create([
                ...$address,
                'description' => 'Central AC unit not cooling - compressor makes loud noise when running.',
                'budget' => 250,
                'preferred_date' => now()->addDays($daysAhead)->toDateString(),
                'preferred_time' => '10:30',
                'created_at' => now()->subHours($index + 1),
            ]);
        }

        // Upcoming Bookings
        foreach ([3, 4] as $index => $daysAhead) {
            $this->createConfirmedBooking($customers[$index], $mohammed, $acRepair, $address, $daysAhead);
        }

        $this->seedNotifications($mohammed, $customers[0], $acRepair);
    }

    /**
     * A paid booking that is waiting for the work date.
     *
     * @param  array<string, mixed>  $address
     */
    private function createConfirmedBooking(User $customer, User $provider, Service $service, array $address, int $daysAhead): void
    {
        $date = now()->addDays($daysAhead)->toDateString();

        $serviceRequest = ServiceRequest::factory()->for($customer, 'customer')->for($service)->create([
            ...$address,
            'description' => 'AC maintenance and gas refill.',
            'budget' => 250,
            'preferred_date' => $date,
            'preferred_time' => '10:30',
            'status' => ServiceRequest::STATUS_ACCEPTED,
        ]);

        $offer = Offer::factory()->for($serviceRequest)->create([
            'provider_id' => $provider->id,
            'price' => 250,
            'status' => Offer::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
        $serviceRequest->forceFill(['accepted_offer_id' => $offer->id])->save();

        $booking = Booking::factory()->create([
            'offer_id' => $offer->id,
            ...$address,
            'scheduled_date' => $date,
            'scheduled_time' => '10:30',
        ]);
        Payment::factory()->for($booking)->create(['user_id' => $customer->id]);
    }

    /**
     * The 4 notifications from the provider Notifications design (the newest one unread).
     */
    private function seedNotifications(User $provider, User $customer, Service $service): void
    {
        $date = ['en' => 'Monday 17 Aug, 2026 10:30 AM', 'ar' => 'الاثنين 17 أغسطس، 2026 10:30 ص'];

        $notifications = [
            ['provider_work_rated', ['customer' => $customer->name, 'rating' => '5'], now()->subHours(3)],
            ['provider_work_started', ['customer' => $customer->name, 'service' => $service->nameTranslations()], now()->subHours(2)],
            ['provider_booking_confirmed', ['customer' => $customer->name, 'service' => $service->nameTranslations(), 'date' => $date], now()->subHour()],
            ['provider_offer_sent', ['customer' => $customer->name, 'service' => $service->nameTranslations()], now()],
        ];

        foreach ($notifications as [$type, $params, $createdAt]) {
            $provider->notifications()->create([
                'id' => Str::uuid()->toString(),
                'type' => $type,
                'data' => ['params' => $params, 'mode' => User::MODE_PROVIDER],
                'read_at' => $type === 'provider_offer_sent' ? null : $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
