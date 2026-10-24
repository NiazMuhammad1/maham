<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\City;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoBookingSeeder extends Seeder
{
    /**
     * Demo data for My Bookings, Booking Details, Rate Provider and Chat (not seeded in production).
     *
     * demo@maham.sa gets (on top of the confirmed booking from DemoMarketplaceSeeder):
     * - 1 booking in progress (Mohammed)
     * - 1 completed booking waiting for a rating (Khalid)
     * - 1 completed booking already rated 5 stars (Mohammed)
     * - a chat with Mohammed Al-Rashidi
     *
     * Runs after DemoMarketplaceSeeder, and only once (skipped when the demo customer already has a booking in progress).
     */
    public function run(): void
    {
        $customer = User::firstWhere('email', 'demo@maham.sa');
        $mohammed = User::firstWhere('username', 'abdul.1523');
        $khalid = User::firstWhere('username', 'khalid.cool');
        $city = City::with('districts')->firstWhere('name_en', 'Riyadh');
        $acRepair = Service::firstWhere('slug', 'ac-repair');

        if (app()->isProduction() || ! $customer || ! $mohammed || ! $khalid || ! $city || ! $acRepair
            || $customer->bookings()->where('status', Booking::STATUS_IN_PROGRESS)->exists()) {
            return;
        }

        $address = [
            'city_id' => $city->id,
            'district_id' => $city->districts->first()?->id,
            'street_address' => '675 King Jamil Rd., 213 Alamal Dist.',
            'postal_code' => '23142',
        ];

        // InProgress tab
        $inProgress = $this->createBooking($customer, $mohammed, $acRepair, $address, [
            'scheduled_date' => today()->toDateString(),
            'status' => Booking::STATUS_IN_PROGRESS,
            'confirmed_at' => now()->subDays(2),
            'started_at' => now()->subHour(),
        ]);

        // Completed tab: "Rate the Service Provider"
        $this->createBooking($customer, $khalid, $acRepair, $address, [
            'scheduled_date' => today()->subDays(3)->toDateString(),
            'status' => Booking::STATUS_COMPLETED,
            'confirmed_at' => now()->subDays(6),
            'started_at' => now()->subDays(3),
            'completed_at' => now()->subDays(3)->addHours(2),
        ]);

        // Completed tab: already rated
        $rated = $this->createBooking($customer, $mohammed, $acRepair, $address, [
            'scheduled_date' => today()->subDays(10)->toDateString(),
            'status' => Booking::STATUS_COMPLETED,
            'confirmed_at' => now()->subDays(12),
            'started_at' => now()->subDays(10),
            'completed_at' => now()->subDays(10)->addHours(2),
        ]);
        Review::factory()->for($rated)->create([
            'rating' => 5,
            'comment' => 'Excellent service! The technician arrived exactly on time for the AC maintenance.',
        ]);
        $mohammed->providerProfile?->refreshRatingStats();

        $this->seedChat($customer, $mohammed, $inProgress);
    }

    /**
     * An accepted request + offer, the booking and its payment.
     *
     * @param  array<string, mixed>  $address
     * @param  array<string, mixed>  $bookingState
     */
    private function createBooking(User $customer, User $provider, Service $service, array $address, array $bookingState): Booking
    {
        $serviceRequest = ServiceRequest::factory()->for($customer, 'customer')->for($service)->create([
            ...$address,
            'description' => 'Central AC unit not cooling - compressor makes loud noise when running.',
            'budget' => 250,
            'preferred_date' => $bookingState['scheduled_date'],
            'preferred_time' => '10:30',
            'status' => ServiceRequest::STATUS_ACCEPTED,
        ]);

        $offer = Offer::factory()->for($serviceRequest)->create([
            'provider_id' => $provider->id,
            'price' => 250,
            'status' => Offer::STATUS_ACCEPTED,
            'accepted_at' => $bookingState['confirmed_at'],
        ]);
        $serviceRequest->forceFill(['accepted_offer_id' => $offer->id])->save();

        $booking = Booking::factory()->create([
            'offer_id' => $offer->id,
            ...$address,
            'scheduled_time' => '10:30',
            ...$bookingState,
        ]);
        Payment::factory()->for($booking)->create(['user_id' => $customer->id, 'paid_at' => $bookingState['confirmed_at']]);

        return $booking;
    }

    /**
     * The conversation from the "Chat (Single)" design. The last message is unread (highlighted in the list).
     */
    private function seedChat(User $customer, User $provider, Booking $booking): void
    {
        $conversation = Conversation::firstOrCreate(
            ['customer_id' => $customer->id, 'provider_id' => $provider->id],
            ['service_request_id' => $booking->service_request_id],
        );
        $conversation->update(['booking_id' => $booking->id]);

        $lines = [
            [$customer, 'Hi, my AC isn\'t cooling properly. Can you help?'],
            [$provider, 'Of course! Could you describe the issue in more detail? Is it leaking or making any noise?'],
            [$customer, 'It\'s making a loud noise and barely cooling the room.'],
            [$provider, 'Great! A verified HVAC specialist will be there tomorrow afternoon to fix your AC.'],
        ];

        $message = null;

        foreach ($lines as $index => [$sender, $body]) {
            $isLast = $index === count($lines) - 1;

            $message = Message::factory()->for($conversation)->create([
                'sender_id' => $sender->id,
                'body' => $body,
                'read_at' => $isLast ? null : now(),
                'created_at' => now()->subMinutes(30 - $index * 5),
            ]);
        }

        $conversation->forceFill([
            'last_message_id' => $message->id,
            'last_message_at' => $message->created_at,
            'customer_unread_count' => 1,
        ])->save();
    }
}
