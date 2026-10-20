<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * "Booking Confirmed" screen: service, provider, date, time, location and price, plus the receipt link.
 *
 * Load `service.media`, `provider.providerProfile`, `city`, `district` and `paidPayment` before using it.
 *
 * @mixin Booking
 */
class BookingResource extends JsonResource
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
        $payment = $this->paidPayment;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'receipt_number' => $this->receipt_number,
            'service_request_id' => $this->service_request_id,
            'status' => $this->status,
            'status_label' => __("messages.booking_status.{$this->status}"),

            'service' => new ServiceResource($this->whenLoaded('service')),
            'provider' => new ProviderResource($this->whenLoaded('provider')),

            'scheduled_date' => $this->scheduled_date->toDateString(),
            'scheduled_date_label' => $this->scheduled_date->locale($locale)->translatedFormat('l d M, Y'),
            'scheduled_time' => $time?->format('H:i'),
            'scheduled_time_label' => $time?->locale($locale)->translatedFormat('h:i A'),

            // "Service Location"
            'address' => [
                'city' => new CityResource($this->whenLoaded('city')),
                'district' => new DistrictResource($this->whenLoaded('district')),
                'street_address' => $this->street_address,
                'postal_code' => $this->postal_code,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],

            // "Service Price"
            'service_price' => (float) $this->service_price,
            'vat_amount' => (float) $this->vat_amount,
            'total_amount' => (float) $this->total_amount,
            'currency' => $this->currency,

            'payment_status' => $this->payment_status,
            'payment_method' => $payment?->method,
            'payment_method_label' => $payment ? __("messages.payment_method.{$payment->method}") : null,
            'paid_at' => $payment?->paid_at?->toIso8601String(),

            // "Download Receipt": open this link in the browser (it works without the login token)
            'receipt_url' => URL::signedRoute('bookings.receipt', ['booking' => $this->id, 'lang' => $locale]),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
