<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\City;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoMarketplaceSeeder extends Seeder
{
    private const string SKILLS = 'Keep your home cool and comfortable. Describe the issue with your AC unit (e.g., not cooling, leaking, or noisy), upload photos for a faster diagnosis, and pick a time that works for you. Your request will be sent to verified HVAC specialists in your area.';

    /**
     * Demo data for My Requests, Offers Received, Offer Details and Provider Details (not seeded in production).
     *
     * - 2 approved providers (Mohammed Al-Rashidi with reviews, Khalid Al-Otaibi)
     * - demo@maham.sa gets: 1 request awaiting offers, 1 request with 2 offers,
     *   1 accepted request with a paid booking, 1 cancelled request.
     *
     * Runs only once (skipped when the demo provider already exists).
     */
    public function run(): void
    {
        $customer = User::firstWhere('email', 'demo@maham.sa');
        $city = City::with('districts')->firstWhere('name_en', 'Riyadh');
        $acRepair = Service::firstWhere('slug', 'ac-repair');

        if (app()->isProduction() || ! $customer || ! $city || ! $acRepair || User::where('username', 'abdul.1523')->exists()) {
            return;
        }

        $mohammed = $this->createProvider('Mohammed Al-Rashidi', 'abdul.1523', '0500000002', ['ac-repair', 'electronics']);
        $khalid = $this->createProvider('Khalid Al-Otaibi', 'khalid.cool', '0500000003', ['ac-repair', 'cleaning']);

        $this->seedReviews($mohammed, $acRepair, $city);

        $address = [
            'city_id' => $city->id,
            'district_id' => $city->districts->first()?->id,
            'street_address' => '675 King Jamil Rd., 213 Alamal Dist.',
            'postal_code' => '23142',
        ];
        $description = 'Central AC unit not cooling - compressor makes loud noise when running.';

        // Open tab: "Awaiting Offers"
        ServiceRequest::factory()->for($customer, 'customer')->for($acRepair)->create([
            ...$address,
            'description' => $description,
            'budget' => 250,
            'preferred_date' => now()->addDays(5)->toDateString(),
            'preferred_time' => '10:30',
        ]);

        // Open tab: "Offers (2)" -> View Offers
        $withOffers = ServiceRequest::factory()->for($customer, 'customer')->for($acRepair)->create([
            ...$address,
            'description' => $description,
            'budget' => 250,
            'preferred_date' => now()->addDays(6)->toDateString(),
            'preferred_time' => '09:00',
        ]);
        Offer::factory()->for($withOffers)->create([
            'provider_id' => $mohammed->id,
            'price' => 250,
            'description' => 'I will check the compressor, refill the gas if needed and clean the filters. Spare parts are not included.',
        ]);
        Offer::factory()->for($withOffers)->proposed(now()->addDays(7)->toDateString(), '16:00')->create([
            'provider_id' => $khalid->id,
            'price' => 220,
            'description' => 'Full inspection and repair of the central unit. I am free one day later in the afternoon.',
        ]);

        // Closed tab: "Offer Accepted" (with a paid booking)
        $accepted = ServiceRequest::factory()->for($customer, 'customer')->for($acRepair)->create([
            ...$address,
            'description' => $description,
            'budget' => 250,
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time' => '09:00',
        ]);
        $acceptedOffer = Offer::factory()->for($accepted)->create([
            'provider_id' => $mohammed->id,
            'price' => 250,
            'status' => Offer::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
        $accepted->forceFill(['status' => ServiceRequest::STATUS_ACCEPTED, 'accepted_offer_id' => $acceptedOffer->id])->save();
        $booking = Booking::factory()->create([
            'offer_id' => $acceptedOffer->id,
            ...$address,
            'scheduled_date' => $accepted->preferred_date->toDateString(),
            'scheduled_time' => '09:00',
        ]);
        Payment::factory()->for($booking)->create(['user_id' => $customer->id]);

        // Closed tab: "Cancelled"
        ServiceRequest::factory()->for($customer, 'customer')->for($acRepair)->cancelled()->create([
            ...$address,
            'description' => $description,
            'budget' => 250,
        ]);
    }

    /**
     * An approved provider with a business profile and the services they work in.
     *
     * @param  list<string>  $serviceSlugs
     */
    private function createProvider(string $name, string $username, string $phone, array $serviceSlugs): User
    {
        $provider = User::factory()->create([
            'name' => $name,
            'username' => $username,
            'email' => $username.'@maham.sa',
            'phone_country_code' => '+966',
            'phone' => substr($phone, 1),
            'phone_verified_at' => now(),
            'registration_completed_at' => now(),
            'is_provider' => true,
        ]);

        ProviderProfile::factory()->for($provider)->create([
            'business_name' => $name,
            'tagline' => 'Professional AC and Electronic Services',
            'skills_experience' => self::SKILLS,
        ]);

        $provider->providedServices()->attach(Service::whereIn('slug', $serviceSlugs)->pluck('id'));

        return $provider;
    }

    /**
     * Completed bookings from other customers, each with a review, so "Customers Rating" has numbers.
     */
    private function seedReviews(User $provider, Service $service, City $city): void
    {
        $ratings = [5, 5, 4, 5, 4, 5, 3, 5];

        foreach ($ratings as $index => $rating) {
            $reviewer = User::factory()->create(['name' => fake()->name(), 'registration_completed_at' => now()]);
            $serviceRequest = ServiceRequest::factory()->for($reviewer, 'customer')->for($service)->create([
                'city_id' => $city->id,
                'status' => ServiceRequest::STATUS_ACCEPTED,
            ]);
            $offer = Offer::factory()->for($serviceRequest)->create([
                'provider_id' => $provider->id,
                'status' => Offer::STATUS_ACCEPTED,
            ]);
            $booking = Booking::factory()->completed()->create(['offer_id' => $offer->id]);

            Review::factory()->for($booking)->create([
                'rating' => $rating,
                'comment' => self::SKILLS,
                'created_at' => now()->subDays(($index + 1) * 3),
            ]);
        }

        $provider->providerProfile->refreshRatingStats();
    }
}
