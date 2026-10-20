<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A service provider as shown on offer cards, "Offer Details" and "Provider Details":
 * photo, name, @username, tagline and "4.9 (312 reviews)".
 *
 * Load `providerProfile` (and `media` for the photo) before using it.
 *
 * @mixin User
 */
class ProviderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->providerProfile;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'avatar_url' => $this->avatarUrl(),
            'tagline' => $profile?->tagline,
            'rating_avg' => round((float) $profile?->rating_avg, 1),
            'reviews_count' => (int) $profile?->reviews_count,

            // Only on Provider Details (when the services are loaded)
            'skills_experience' => $this->when($this->relationLoaded('providedServices'), $profile?->skills_experience),
            'services' => ServiceResource::collection($this->whenLoaded('providedServices')),
        ];
    }
}
