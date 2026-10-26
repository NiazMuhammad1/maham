<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Bookings of the logged-in customer:
 *
 * My Bookings (Confirmed / InProgress / Completed tabs) -> "View Details" -> Booking Details
 * -> "View Receipt" / "Chat" / "Rate the Service Provider" -> "Review Submitted" popup.
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
     * My Bookings list. ?tab=confirmed (default), in_progress or completed.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['tab' => ['nullable', Rule::in(Booking::TABS)]]);

        $tab = $request->input('tab', Booking::STATUS_CONFIRMED);
        $query = $request->user()->bookings()->where('status', $tab)->with(self::RELATIONS);

        // Upcoming work: nearest date first. Completed work: last finished first.
        if ($tab === Booking::STATUS_COMPLETED) {
            $query->latest('completed_at');
        } else {
            $query->orderBy('scheduled_date')->orderBy('scheduled_time');
        }

        $bookings = $query->paginate(20);

        return $this->success([
            'bookings' => BookingResource::collection($bookings)->resolve(),
            'current_page' => $bookings->currentPage(),
            'last_page' => $bookings->lastPage(),
            'total' => $bookings->total(),
        ]);
    }

    /**
     * Booking Details (also the Booking Confirmed screen, or opened from a notification).
     */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->ensureOwnsBooking($request, $booking);

        $booking->load(self::RELATIONS);

        return $this->success(new BookingResource($booking));
    }

    /**
     * "Submit Review" on the Rate Provider sheet. Only once per booking, and only after the work is completed.
     * The provider's "4.9 (312 reviews)" is recalculated straight away.
     */
    public function review(StoreReviewRequest $request, Booking $booking): JsonResponse
    {
        $this->ensureOwnsBooking($request, $booking);

        if ($booking->status !== Booking::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['booking' => __('messages.review_not_allowed')]);
        }

        if ($booking->review()->exists()) {
            throw ValidationException::withMessages(['booking' => __('messages.review_already_submitted')]);
        }

        $review = $booking->review()->create([
            'customer_id' => $booking->customer_id,
            'provider_id' => $booking->provider_id,
            'rating' => $request->integer('rating'),
            'comment' => $request->input('comment'),
        ]);

        $booking->provider->providerProfile?->refreshRatingStats();

        // "Work completed ... has rated your service" in the provider's Notifications list
        $booking->provider->addProviderNotification('provider_work_rated', [
            'customer' => $booking->customer->name,
            'rating' => (string) $review->rating,
        ], ['booking_id' => $booking->id]);

        $review->load('customer.media');

        return $this->success(new ReviewResource($review), __('messages.review_submitted'), 201);
    }

    /**
     * "Download Receipt": a printable HTML page (the phone can print it or save it as PDF).
     *
     * Opened from `receipt_url`, which is a signed link, so it works in the browser without the login token
     * and nobody can open another booking's receipt by changing the id.
     */
    public function receipt(Request $request, Booking $booking): View
    {
        app()->setLocale($request->query('lang') === 'ar' ? 'ar' : 'en');

        $booking->load(['service', 'customer', 'provider', 'city', 'district', 'paidPayment']);

        return view('receipts.booking', ['booking' => $booking]);
    }

    /**
     * Customers can only see their own bookings (404 otherwise).
     */
    private function ensureOwnsBooking(Request $request, Booking $booking): void
    {
        abort_unless($booking->customer_id === $request->user()->id, 404);
    }
}
