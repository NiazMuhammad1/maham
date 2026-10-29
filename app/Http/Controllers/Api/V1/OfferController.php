<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptOfferRequest;
use App\Http\Requests\StartCheckoutRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\OfferResource;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer side of offers:
 *
 * My Requests -> "View Offers" -> Offers Received -> "View Offer" -> Offer Details
 * -> "Accept Offer" -> Confirm Details -> "Book Service" -> Payments -> "Confirm and Pay" -> Booking Confirmed.
 *
 * Confirm Details and Payments are built on the phone from the Offer Details response;
 * only "Confirm and Pay" calls the server (accept).
 */
class OfferController extends Controller
{
    /**
     * Offers Received: the request at the top ("AC Repair  REQ-2041  3 Offers") and the offer cards.
     */
    public function index(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->ensureOwnsRequest($request, $serviceRequest);

        $offers = $serviceRequest->offers()
            ->where('status', '!=', Offer::STATUS_WITHDRAWN)
            ->with(['provider.providerProfile', 'provider.media'])
            ->latest()
            ->paginate(20);

        // Every offer belongs to this request, so reuse it instead of loading it again for each offer
        $offers->each(fn (Offer $offer) => $offer->setRelation('serviceRequest', $serviceRequest));

        $serviceRequest->load(['service.media', 'city', 'district', 'media']);

        return $this->success([
            'service_request' => (new ServiceRequestResource($serviceRequest))->resolve(),
            'offers' => OfferResource::collection($offers)->resolve(),
            'current_page' => $offers->currentPage(),
            'last_page' => $offers->lastPage(),
            'total' => $offers->total(),
        ]);
    }

    /**
     * Offer Details. Also has everything for the "Confirm Details" and "Payments" sheets:
     * the request (service, work address) and the payment settings.
     */
    public function show(Request $request, Offer $offer, PaymentService $paymentService): JsonResponse
    {
        $this->ensureOwnsRequest($request, $offer->serviceRequest);

        $offer->load(['provider.providerProfile', 'provider.media', 'serviceRequest.service.media', 'serviceRequest.city', 'serviceRequest.district', 'serviceRequest.media']);

        $price = $offer->priceBreakdown();

        return $this->success([
            ...(new OfferResource($offer))->resolve(),
            'can_accept' => $offer->status === Offer::STATUS_PENDING && $offer->serviceRequest->isOpen(),
            'service_request' => (new ServiceRequestResource($offer->serviceRequest))->resolve(),
            'payment' => [
                ...$paymentService->clientConfig(),
                // Payments sheet: "Service Price" + "Processing fee" = "Total" (the amount to charge)
                'service_price' => $price['service_price'],
                'processing_fee_percent' => $price['processing_fee_percent'],
                'processing_fee' => $price['processing_fee'],
                'amount' => $price['total_amount'],
                'amount_halalas' => (int) round($price['total_amount'] * 100), // Moyasar SDK wants the amount in halalas
                'currency' => $offer->currency,
                'methods' => collect($paymentService->availableMethods())->map(fn (string $method) => [
                    'value' => $method,
                    'label' => __("messages.payment_method.{$method}"),
                    // true = call "Start checkout" and open checkout_url in a WebView (Tabby / Tamara)
                    'uses_checkout_page' => in_array($method, Payment::INSTALLMENT_METHODS, true),
                ]),
            ],
        ]);
    }

    /**
     * Payments sheet with Tabby / Tamara selected -> "Confirm and Pay": open the gateway's checkout.
     * The app opens checkout_url in a WebView; when it reaches the success link,
     * it calls "accept" with the same payment_method and payment_id.
     */
    public function checkout(StartCheckoutRequest $request, Offer $offer, PaymentService $paymentService): JsonResponse
    {
        $this->ensureOwnsRequest($request, $offer->serviceRequest);
        $this->ensureCanBeAccepted($offer);

        $method = $request->input('payment_method');

        if (! in_array($method, $paymentService->availableMethods(), true)) {
            throw ValidationException::withMessages(['payment_method' => __('messages.installment_not_available')]);
        }

        $offer->load(['serviceRequest.service', 'serviceRequest.city']);
        $amount = $offer->priceBreakdown()['total_amount'];
        $checkout = $paymentService->createCheckout($method, $offer, $request->user(), $amount);

        return $this->success([
            'payment_method' => $method,
            'payment_id' => $checkout['payment_id'],
            'checkout_url' => $checkout['checkout_url'],
            'amount' => $amount,
            'currency' => $offer->currency,
            // The WebView watches for these links: success -> call accept, cancel / failure -> back to the Payments sheet
            'redirect_urls' => config('services.payment.redirect_urls'),
        ]);
    }

    /**
     * "Confirm and Pay": check the payment, then in one database transaction
     * accept this offer, reject the other offers, close the request and create the booking.
     */
    public function accept(AcceptOfferRequest $request, Offer $offer, PaymentService $paymentService): JsonResponse
    {
        $serviceRequest = $offer->serviceRequest;
        $this->ensureOwnsRequest($request, $serviceRequest);
        $this->ensureCanBeAccepted($offer);

        // The customer pays the price + processing fee; the provider earns the price - commission
        $price = $offer->priceBreakdown();

        // Throws a 422 when the gateway says the payment is not paid / wrong amount
        $payment = $paymentService->verify(
            $request->input('payment_method'),
            $request->input('payment_id'),
            $price['total_amount'],
            $offer->currency,
            PaymentService::reference($offer),
        );
        $booking = DB::transaction(function () use ($request, $offer, $serviceRequest, $payment, $price) {
            // Lock the request so two taps on "Confirm and Pay" cannot create two bookings
            $serviceRequest = ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);
            $offer->refresh()->setRelation('serviceRequest', $serviceRequest);
            $this->ensureCanBeAccepted($offer);

            $offer->forceFill(['status' => Offer::STATUS_ACCEPTED, 'accepted_at' => now()])->save();

            $serviceRequest->offers()
                ->whereKeyNot($offer->id)
                ->where('status', Offer::STATUS_PENDING)
                ->update(['status' => Offer::STATUS_REJECTED]);

            $serviceRequest->forceFill([
                'status' => ServiceRequest::STATUS_ACCEPTED,
                'accepted_offer_id' => $offer->id,
            ])->save();

            // The booking keeps its own copy of the date, address and price
            $booking = Booking::create([
                'service_request_id' => $serviceRequest->id,
                'offer_id' => $offer->id,
                'customer_id' => $serviceRequest->customer_id,
                'provider_id' => $offer->provider_id,
                'service_id' => $serviceRequest->service_id,
                'scheduled_date' => $offer->scheduledDate()->toDateString(),
                'scheduled_time' => $offer->scheduledTime(),
                'city_id' => $serviceRequest->city_id,
                'district_id' => $serviceRequest->district_id,
                'street_address' => $serviceRequest->street_address,
                'postal_code' => $serviceRequest->postal_code,
                'latitude' => $serviceRequest->latitude,
                'longitude' => $serviceRequest->longitude,
                'service_price' => $price['service_price'],
                'vat_amount' => 0,
                'platform_fee' => $price['processing_fee'], // "Processing fee" paid by the customer
                'total_amount' => $price['total_amount'],
                'provider_earning' => $price['provider_earning'], // price - Maham's commission
                'currency' => $offer->currency,
                'status' => Booking::STATUS_CONFIRMED,
                'payment_status' => 'paid',
                'confirmed_at' => now(),
            ]);

            $booking->payments()->create([
                'user_id' => $request->user()->id,
                'gateway' => $payment['gateway'],
                'method' => $request->input('payment_method'),
                'transaction_id' => $payment['transaction_id'],
                'amount' => $price['total_amount'],
                'currency' => $offer->currency,
                'status' => 'paid',
                'gateway_response' => $payment['response'],
                'paid_at' => now(),
            ]);

            return $booking;
        });

        $booking->load(['service.media', 'provider.providerProfile', 'provider.media', 'city', 'district', 'paidPayment']);

        $bookingData = (new BookingResource($booking))->resolve();

        // "Payment successful" in the customer's Notifications list (booking_id tells the app which booking to open)
        // Service name and date are saved in both languages, so each reader sees their own language.
        $request->user()->addAppNotification('payment_successful', [
            'service' => $booking->service->nameTranslations(),
            'date' => $booking->scheduledAtTranslations(),
        ], ['booking_id' => $booking->id, 'service_request_id' => $serviceRequest->id]);

        // "Booking Confirmed!" in the provider's Notifications list
        $booking->provider->addProviderNotification('provider_booking_confirmed', [
            'customer' => $request->user()->name,
            'service' => $booking->service->nameTranslations(),
            'date' => $booking->scheduledAtTranslations(),
        ], ['booking_id' => $booking->id, 'service_request_id' => $serviceRequest->id]);

        return $this->success($bookingData, __('messages.booking_confirmed'), 201);
    }

    /**
     * Customers can only see offers on their own requests (404 otherwise, like Request Details).
     */
    private function ensureOwnsRequest(Request $request, ServiceRequest $serviceRequest): void
    {
        abort_unless($serviceRequest->customer_id === $request->user()->id, 404);
    }

    /**
     * Only a pending offer on an open request can be accepted.
     */
    private function ensureCanBeAccepted(Offer $offer): void
    {
        if ($offer->status !== Offer::STATUS_PENDING || ! $offer->serviceRequest->isOpen()) {
            throw ValidationException::withMessages(['offer' => __('messages.offer_not_available')]);
        }
    }
}
