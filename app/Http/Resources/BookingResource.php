<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * "Booking Confirmed", "My Bookings" cards and "Booking Details": service, provider, date, time,
 * location and price, the receipt link, the status timeline and the customer's review.
 *
 * Load `service.media`, `provider.providerProfile`, `city`, `district`, `paidPayment` and `review` before using it.
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

            // "Service Price" + "Processing fee" (+ VAT) = "Total"
            'service_price' => (float) $this->service_price,
            'processing_fee' => (float) $this->platform_fee,
            'vat_amount' => (float) $this->vat_amount,
            'total_amount' => (float) $this->total_amount,
            'currency' => $this->currency,

            'payment_status' => $this->payment_status,
            'payment_method' => $payment?->method,
            'payment_method_label' => $payment ? __("messages.payment_method.{$payment->method}") : null,
            'paid_at' => $payment?->paid_at?->toIso8601String(),

            // "Download Receipt": open this link in the browser (it works without the login token)
            'receipt_url' => URL::signedRoute('bookings.receipt', ['booking' => $this->id, 'lang' => $locale]),

            // Booking Details: Booking Confirmed -> Work InProgress -> Completed
            'timeline' => $this->timeline($locale, $time),

            // "Rate the Service Provider" button, and the stars + comment after rating
            'can_review' => $this->canBeReviewed(),
            'review' => $this->review ? (new ReviewResource($this->review))->resolve() : null,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * The 3 steps on Booking Details. `is_done` = the step happened (green icon),
     * `is_current` = the latest step that happened. `message` is the text in the white box under the step.
     *
     * @return list<array{key: string, title: string, is_done: bool, is_current: bool, date: ?string, date_label: ?string, message: ?string}>
     */
    private function timeline(string $locale, ?Carbon $time): array
    {
        $provider = $this->provider?->name;

        $steps = [
            Booking::STATUS_CONFIRMED => [
                'date' => $this->confirmed_at,
                'message' => __('messages.booking_timeline.confirmed.message', [
                    'service' => $this->service?->localized('name'),
                    'date' => $this->scheduled_date->locale($locale)->translatedFormat('l, d M Y'),
                    'time' => $time?->locale($locale)->translatedFormat('h:i A'),
                ]),
            ],
            Booking::STATUS_IN_PROGRESS => [
                'date' => $this->started_at,
                'message' => __('messages.booking_timeline.in_progress.message', ['provider' => $provider]),
            ],
            Booking::STATUS_COMPLETED => [
                'date' => $this->completed_at,
                'message' => $this->review
                    ? __('messages.booking_timeline.completed.message_reviewed')
                    : __('messages.booking_timeline.completed.message', ['provider' => $provider]),
            ],
        ];

        $currentStep = collect($steps)->filter(fn (array $step) => $step['date'] !== null)->keys()->last();

        return collect($steps)->map(fn (array $step, string $key) => [
            'key' => $key,
            'title' => __("messages.booking_timeline.{$key}.title"),
            'is_done' => $step['date'] !== null,
            'is_current' => $key === $currentStep,
            'date' => $step['date']?->toIso8601String(),
            'date_label' => $step['date']?->locale($locale)->translatedFormat('l d M, Y'),
            // No text under steps that did not happen yet
            'message' => $step['date'] !== null ? $step['message'] : null,
        ])->values()->all();
    }
}
