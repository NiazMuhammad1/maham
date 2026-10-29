<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * A booking as the provider sees it in short lists: "Upcoming Bookings" on the provider Home
 * and the "Bookings" list on Earnings. Shows the customer instead of the provider.
 *
 * Load `service.media`, `customer.media`, `city` and `district` before using it.
 *
 * @mixin Booking
 */
class ProviderBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $time = $this->scheduled_time ? Carbon::createFromTimeString($this->scheduled_time) : null;
        $customer = $this->customer;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'status_label' => __("messages.booking_status.{$this->status}"),
            'service' => new ServiceResource($this->whenLoaded('service')),

            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'username' => $customer->username,
                'avatar_url' => $customer->avatarUrl(),
            ] : null,

            // "09 Aug" / "10:30AM" on Upcoming Bookings, "Sunday 09 Aug, 2026 - 10:30AM" on Earnings
            'scheduled_date' => $this->scheduled_date->toDateString(),
            'scheduled_date_label' => $this->scheduled_date->locale($locale)->translatedFormat('l d M, Y'),
            'scheduled_day_label' => $this->scheduled_date->locale($locale)->translatedFormat('d M'),
            'scheduled_time' => $time?->format('H:i'),
            'scheduled_time_label' => $time?->locale($locale)->translatedFormat('h:i A'),

            // "Alamal, Riyadh"
            'address' => [
                'city' => new CityResource($this->whenLoaded('city')),
                'district' => new DistrictResource($this->whenLoaded('district')),
                'street_address' => $this->street_address,
            ],

            // "SAR 250": the price of the job, Maham's commission, and what the provider receives for it
            'service_price' => (float) $this->service_price,
            'commission' => round((float) $this->service_price - (float) $this->provider_earning, 2),
            'provider_earning' => (float) $this->provider_earning,
            'currency' => $this->currency,

            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
