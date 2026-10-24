<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Bookings of the logged-in provider: "Start Work" and "Complete Work".
 *
 * Confirmed -> In Progress -> Completed. Each step fills the date used by the customer's Booking Details timeline
 * and sends the customer a notification.
 */
class BookingController extends Controller
{
    /** Everything BookingResource shows. */
    private const array RELATIONS = [
        'service.media',
        'provider.providerProfile',
        'provider.media',
        'city',
        'district',
        'paidPayment',
        'review.customer.media',
    ];

    /**
     * "Start Work": confirmed -> in_progress.
     */
    public function start(Request $request, Booking $booking): JsonResponse
    {
        $this->ensureProvidesBooking($request, $booking);

        if ($booking->status !== Booking::STATUS_CONFIRMED) {
            throw ValidationException::withMessages(['booking' => __('messages.booking_cannot_be_started')]);
        }

        $booking->update([
            'status' => Booking::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $booking->customer->addAppNotification('work_started', [
            'provider' => $booking->provider->name,
        ], ['booking_id' => $booking->id]);

        $booking->load(self::RELATIONS);

        return $this->success(new BookingResource($booking), __('messages.work_started'));
    }

    /**
     * "Complete Work": in_progress -> completed. The customer can now rate the provider.
     */
    public function complete(Request $request, Booking $booking): JsonResponse
    {
        $this->ensureProvidesBooking($request, $booking);

        if ($booking->status !== Booking::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages(['booking' => __('messages.booking_cannot_be_completed')]);
        }

        $booking->update([
            'status' => Booking::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $booking->customer->addAppNotification('work_completed', [
            'provider' => $booking->provider->name,
        ], ['booking_id' => $booking->id]);

        $booking->load(self::RELATIONS);

        return $this->success(new BookingResource($booking), __('messages.work_completed'));
    }

    /**
     * Providers can only change their own bookings (404 otherwise).
     */
    private function ensureProvidesBooking(Request $request, Booking $booking): void
    {
        abort_unless($booking->provider_id === $request->user()->id, 404);
    }
}
