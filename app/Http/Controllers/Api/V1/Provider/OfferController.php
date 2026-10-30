<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\SendOfferRequest;
use App\Http\Resources\OfferResource;
use App\Http\Resources\ProviderServiceRequestResource;
use App\Models\Offer;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Provider side of offers:
 *
 * Request Details -> "Send Offer" -> "Offer Sent Successfully!"
 * Offer Sent tab -> "View Offer" -> Offer Details -> "Send a Message" / "Withdraw Offer".
 */
class OfferController extends Controller
{
    /**
     * "Send Offer": save the offer, then notify the customer ("Offer Received") and the provider ("Offer Sent").
     */
    public function store(SendOfferRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $provider = $request->user();
        $this->ensureProviderCanUseDashboard($provider);

        $offer = DB::transaction(function () use ($request, $serviceRequest, $provider) {
            // Lock the request so a double tap on "Send Offer" cannot send two offers
            ServiceRequest::lockForUpdate()->findOrFail($serviceRequest->id);

            // Open, in the provider's services and area, not in the past, and no offer from them yet
            if (! $serviceRequest->canReceiveOfferFrom($provider)) {
                throw ValidationException::withMessages(['service_request' => __('messages.request_not_available')]);
            }

            $offer = $serviceRequest->offers()->create([
                ...$request->validated(),
                'provider_id' => $provider->id,
            ]);

            // "Offers (3)" on the customer's My Requests card
            $serviceRequest->increment('offers_count');

            return $offer;
        });

        $customer = $serviceRequest->customer;
        $service = $serviceRequest->service->nameTranslations();

        // "Offer Received" in the customer's Notifications list
        $customer->addAppNotification('offer_received', [
            'provider' => $provider->name,
            'service' => $service,
        ], ['service_request_id' => $serviceRequest->id, 'offer_id' => $offer->id]);

        // "Offer Sent" in the provider's own Notifications list
        $provider->addProviderNotification('provider_offer_sent', [
            'customer' => $customer->name,
            'service' => $service,
        ], ['service_request_id' => $serviceRequest->id, 'offer_id' => $offer->id]);

        return $this->success($this->offerDetails($offer), __('messages.offer_sent'), 201);
    }

    /**
     * Offer Details (provider's own offer).
     */
    public function show(Request $request, Offer $offer): JsonResponse
    {
        $this->ensureOwnsOffer($request, $offer);

        return $this->success($this->offerDetails($offer));
    }

    /**
     * "Withdraw Offer": only while the offer is pending and the request is still open.
     * The offer leaves the customer's "Offers Received" list and moves to the provider's Closed tab.
     */
    public function withdraw(Request $request, Offer $offer): JsonResponse
    {
        $this->ensureOwnsOffer($request, $offer);

        DB::transaction(function () use ($offer) {
            // Lock the request so the customer cannot accept the offer at the same moment
            $serviceRequest = ServiceRequest::lockForUpdate()->findOrFail($offer->service_request_id);
            $offer->refresh()->setRelation('serviceRequest', $serviceRequest);

            if (! $this->canBeWithdrawn($offer)) {
                throw ValidationException::withMessages(['offer' => __('messages.offer_cannot_be_withdrawn')]);
            }

            $offer->forceFill(['status' => Offer::STATUS_WITHDRAWN])->save();
            $serviceRequest->decrement('offers_count');
        });

        return $this->success($this->offerDetails($offer), __('messages.offer_withdrawn'));
    }

    /**
     * Everything on Offer Details: the offer, the request (customer, service, budget, address)
     * and what the provider will receive for it.
     *
     * @return array<string, mixed>
     */
    private function offerDetails(Offer $offer): array
    {
        $offer->load(['serviceRequest.service.media', 'serviceRequest.customer.media', 'serviceRequest.city', 'serviceRequest.district', 'serviceRequest.media']);

        // The request's "my_offer" is this offer
        $offer->serviceRequest->setRelation('offers', new Collection([$offer]));

        $price = $offer->priceBreakdown();

        return [
            ...(new OfferResource($offer))->resolve(),
            'can_withdraw' => $this->canBeWithdrawn($offer),
            'service_request' => (new ProviderServiceRequestResource($offer->serviceRequest))->resolve(),

            // Offer price - Maham commission = what the provider receives (paid out on the weekly payout)
            'earning' => [
                'service_price' => $price['service_price'],
                'commission' => $price['provider_commission'],
                'provider_earning' => $price['provider_earning'],
                'currency' => $offer->currency,
            ],
        ];
    }

    /**
     * Only a pending offer on an open request can be withdrawn.
     */
    private function canBeWithdrawn(Offer $offer): bool
    {
        return $offer->status === Offer::STATUS_PENDING && $offer->serviceRequest->isOpen();
    }

    /**
     * Approved providers can only see and change their own offers (404 otherwise).
     */
    private function ensureOwnsOffer(Request $request, Offer $offer): void
    {
        $this->ensureProviderCanUseDashboard($request->user());
        abort_unless($offer->provider_id === $request->user()->id, 404);
    }
}
