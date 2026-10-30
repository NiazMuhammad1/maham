<?php

namespace App\Http\Resources;

use App\Models\Offer;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

/**
 * A customer's request as the provider sees it on "Customer Requests" and Request Details:
 * everything from ServiceRequestResource, plus the customer, the provider's own offer and the tab badge.
 *
 * Load `customer.media` and only the provider's own offer as `offers`
 * (with(['offers' => fn ($query) => $query->where('provider_id', $provider->id)])).
 *
 * @mixin ServiceRequest
 */
class ProviderServiceRequestResource extends ServiceRequestResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $myOffer = $this->relationLoaded('offers') ? $this->offers->first() : null;

        // Badge on the card: Open (no offer yet), Offer Sent (waiting for the customer) or Closed
        $providerStatus = match (true) {
            $myOffer === null => 'open',
            $myOffer->status === Offer::STATUS_PENDING && $this->isOpen() => 'offer_sent',
            default => 'closed',
        };

        return [
            ...parent::toArray($request),

            'provider_status' => $providerStatus,
            'provider_status_label' => __("messages.provider_request_status.{$providerStatus}"),

            // "Abdul Rehman  abdul1523" at the top of Request Details
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'username' => $this->customer->username,
                'avatar_url' => $this->customer->avatarUrl(),
            ]),

            // The provider's own offer (null on the Open tab). "View Offer" opens provider/offers/{id}.
            'my_offer' => $myOffer ? [
                'id' => $myOffer->id,
                'status' => $myOffer->status,
                'status_label' => __("messages.offer_status.{$myOffer->status}"),
                'price' => (float) $myOffer->price,
                'currency' => $myOffer->currency,
            ] : null,
        ];
    }
}
