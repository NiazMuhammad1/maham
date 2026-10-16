<?php

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Everything shown on the "Confirm Request" screen and in "My Requests".
 * The `*_label` fields are already translated (Accept-Language: ar / en) so the app can show them as they are.
 *
 * @mixin ServiceRequest
 */
class ServiceRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $time = $this->preferred_time ? Carbon::createFromTimeString($this->preferred_time) : null;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'status_label' => __("messages.request_status.{$this->status}"),

            // "Requested service"
            'service' => new ServiceResource($this->whenLoaded('service')),

            // "Work description and budget"
            'description' => $this->description,
            'budget' => $this->budget !== null ? (float) $this->budget : null,
            'currency' => $this->currency,

            // "Date and time" - e.g. 2026-08-09 / "Sunday 09 Aug, 2026" / "09:00" / "09:00 AM"
            'preferred_date' => $this->preferred_date->toDateString(),
            'preferred_date_label' => $this->preferred_date->locale(app()->getLocale())->translatedFormat('l d M, Y'),
            'preferred_time' => $time?->format('H:i'),
            'preferred_time_label' => $time?->locale(app()->getLocale())->translatedFormat('h:i A'),
            'flexibility' => $this->flexibility,
            'flexibility_label' => __("messages.flexibility.{$this->flexibility}"),

            // "Work address"
            'address' => [
                'address_id' => $this->address_id,
                'city' => new CityResource($this->whenLoaded('city')),
                'district' => new DistrictResource($this->whenLoaded('district')),
                'street_address' => $this->street_address,
                'postal_code' => $this->postal_code,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],

            // "Images" (Spatie Media Library collection "images")
            'images' => $this->getMedia('images')->map(fn (Media $media) => [
                'id' => $media->id,
                'url' => $media->getUrl(),
            ])->values(),

            'offers_count' => $this->offers_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
