<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The "Become a Service Provider" application: its status, the next screen and everything saved so far
 * (so the app can pre-fill the steps when the user comes back).
 *
 * Load `providerProfile.media`, `providedServices.media` and `serviceAreas.city` / `serviceAreas.district` first.
 *
 * @mixin User
 */
class ProviderApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->providerProfile;
        $serviceArea = $this->serviceAreas->first();

        return [
            'status' => $this->providerApplicationStatus(),
            'next_step' => $this->nextProviderApplicationStep(),
            'can_edit' => $profile === null || $profile->isEditable(),
            'rejection_reason' => $profile?->rejection_reason,
            'submitted_at' => $profile?->submitted_at?->toIso8601String(),

            // Step 1: Select Service
            'service_ids' => $this->providedServices->pluck('id'),
            'services' => ServiceResource::collection($this->providedServices),

            // Step 2: Choose Service Area
            'service_area' => $serviceArea ? [
                'city' => new CityResource($serviceArea->city),
                'district' => $serviceArea->district ? new DistrictResource($serviceArea->district) : null,
                'postal_code' => $serviceArea->postal_code,
            ] : null,

            // Step 3: Business Details
            'business_name' => $profile?->business_name,
            'tagline' => $profile?->tagline,
            'skills_experience' => $profile?->skills_experience,
            'website' => $profile?->website,

            // Step 4: Upload Business Logo
            'logo_url' => $profile?->logoUrl(),
        ];
    }
}
