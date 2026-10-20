<?php

namespace App\Http\Resources;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * One offer card on "Offers Received" (also the top of "Offer Details").
 *
 * `date` / `time` are when the work will happen: the provider's proposed date and time,
 * or the customer's own date and time when the provider agreed with it.
 * Load `serviceRequest` and `provider.providerProfile` before using it.
 *
 * @mixin Offer
 */
class OfferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $date = $this->scheduledDate();
        $time = $this->scheduledTime() ? Carbon::createFromTimeString($this->scheduledTime()) : null;
        $flexibility = $this->serviceRequest->flexibility;

        return [
            'id' => $this->id,
            'service_request_id' => $this->service_request_id,
            'status' => $this->status,
            'status_label' => __("messages.offer_status.{$this->status}"),

            'provider' => new ProviderResource($this->whenLoaded('provider')),

            // "Work description and budget" on Offer Details
            'description' => $this->description,
            'price' => (float) $this->price,
            'currency' => $this->currency,

            // "Date and time" - same formats as the request
            'schedule_type' => $this->schedule_type,
            'date' => $date->toDateString(),
            'date_label' => $date->locale($locale)->translatedFormat('l d M, Y'),
            'time' => $time?->format('H:i'),
            'time_label' => $time?->locale($locale)->translatedFormat('h:i A'),
            'flexibility' => $flexibility,
            'flexibility_label' => __("messages.flexibility.{$flexibility}"),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
