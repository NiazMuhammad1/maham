<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bookings of the logged-in customer.
 */
class BookingController extends Controller
{
    /**
     * One booking (Booking Confirmed screen, or opened from a "Payment successful" notification).
     */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->customer_id === $request->user()->id, 404);

        $booking->load(['service.media', 'provider.providerProfile', 'provider.media', 'city', 'district', 'paidPayment']);

        return $this->success(new BookingResource($booking));
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
}
