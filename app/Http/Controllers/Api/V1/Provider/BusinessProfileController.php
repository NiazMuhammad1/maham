<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\ApplicationBusinessDetailsRequest;
use App\Http\Requests\Provider\ApplicationServiceAreaRequest;
use App\Http\Requests\Provider\ApplicationServicesRequest;
use App\Http\Resources\CityResource;
use App\Http\Resources\DistrictResource;
use App\Http\Resources\ServiceResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Provider side menu -> "Business Profile": the header card ("View Profile") and the Business Settings
 * screens Manage Services, Service Area and Business Details. Each "Save Changes" shows "Changes Saved".
 *
 * "View Profile" opens the public profile: GET /providers/{id} (+ /providers/{id}/reviews for "View More").
 * "Payment Settings" is in PayoutAccountController.
 *
 * Unlike the "Become a Service Provider" steps, an approved provider can change these at any time
 * (no new admin review). They use the same validation as the application steps.
 */
class BusinessProfileController extends Controller
{
    /**
     * Business Profile screen + the saved values used to pre-fill the three settings screens.
     */
    public function show(Request $request): JsonResponse
    {
        $provider = $request->user();
        $this->ensureProviderCanUseDashboard($provider);

        return $this->businessProfileResponse($provider);
    }

    /**
     * "Manage Services": replaces the categories the provider receives broadcast requests for.
     * The app lists every category with GET /services and ticks the ones in `service_ids`.
     */
    public function updateServices(ApplicationServicesRequest $request): JsonResponse
    {
        $provider = $request->user();
        $this->ensureProviderCanUseDashboard($provider);

        $provider->providedServices()->sync($request->validated('service_ids'));

        return $this->businessProfileResponse($provider, __('messages.changes_saved'));
    }

    /**
     * "Service Area": one city (and optional district = only that district), replaces the previous one.
     */
    public function updateServiceArea(ApplicationServiceAreaRequest $request): JsonResponse
    {
        $provider = $request->user();
        $this->ensureProviderCanUseDashboard($provider);

        $provider->serviceAreas()->delete();
        $provider->serviceAreas()->create($request->validated());

        return $this->businessProfileResponse($provider, __('messages.changes_saved'));
    }

    /**
     * "Business Details": business name, professional tagline, skills and experience, website.
     */
    public function updateBusinessDetails(ApplicationBusinessDetailsRequest $request): JsonResponse
    {
        $provider = $request->user();
        $this->ensureProviderCanUseDashboard($provider);

        $provider->providerProfile->update($request->validated());

        return $this->businessProfileResponse($provider, __('messages.changes_saved'));
    }

    private function businessProfileResponse(User $provider, ?string $message = null): JsonResponse
    {
        $provider->load([
            'media',
            'providerProfile.media',
            'providedServices' => fn ($query) => $query->orderBy('sort_order')->with('media'),
            'serviceAreas.city',
            'serviceAreas.district',
        ]);

        $profile = $provider->providerProfile;
        $serviceArea = $provider->serviceAreas->first();

        return $this->success([
            // Header card: photo, name, @username, tagline, "4.9 (312 reviews)" + "View Profile" (uses `id`)
            'provider' => [
                'id' => $provider->id,
                'name' => $provider->name,
                'username' => $provider->username,
                'avatar_url' => $provider->avatarUrl(),
                'logo_url' => $profile->logoUrl(),
                'tagline' => $profile->tagline,
                'rating_avg' => round((float) $profile->rating_avg, 1),
                'reviews_count' => (int) $profile->reviews_count,
            ],

            // Manage Services
            'service_ids' => $provider->providedServices->pluck('id'),
            'services' => ServiceResource::collection($provider->providedServices)->resolve(),

            // Service Area
            'service_area' => $serviceArea ? [
                'city' => new CityResource($serviceArea->city),
                'district' => $serviceArea->district ? new DistrictResource($serviceArea->district) : null,
                'postal_code' => $serviceArea->postal_code,
            ] : null,

            // Business Details
            'business_details' => [
                'business_name' => $profile->business_name,
                'tagline' => $profile->tagline,
                'skills_experience' => $profile->skills_experience,
                'website' => $profile->website,
            ],

            // Payment Settings: false = show a "add your bank account" hint
            'has_payout_account' => $provider->payoutAccount()->exists(),
        ], $message);
    }
}
