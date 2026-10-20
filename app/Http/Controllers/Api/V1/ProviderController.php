<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderResource;
use App\Http\Resources\ReviewResource;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * "View Profile" on Offer Details -> Provider Details -> "View More" -> Customers Reviews.
 */
class ProviderController extends Controller
{
    /**
     * Provider Details: profile, skills and experience, services, rating bars and the latest 3 reviews.
     */
    public function show(User $provider): JsonResponse
    {
        $this->ensureIsApprovedProvider($provider);

        $provider->load([
            'providerProfile',
            'media',
            'providedServices' => fn ($query) => $query->active()->orderBy('sort_order')->with('media'),
        ]);

        // How many reviews have 5, 4, 3, 2 and 1 stars (for the "Customers Rating" bars)
        $countsByStars = $provider->receivedReviews()->visible()
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $ratingBreakdown = collect([5, 4, 3, 2, 1])->map(fn (int $stars) => [
            'stars' => $stars,
            'count' => (int) $countsByStars->get($stars, 0),
        ]);

        $latestReviews = $provider->receivedReviews()->visible()
            ->with('customer.media')
            ->latest()
            ->limit(3)
            ->get();

        return $this->success([
            ...(new ProviderResource($provider))->resolve(),
            'rating_breakdown' => $ratingBreakdown,
            'latest_reviews' => ReviewResource::collection($latestReviews)->resolve(),
        ]);
    }

    /**
     * Customers Reviews sheet ("View More"), newest first, 20 per page.
     */
    public function reviews(User $provider): JsonResponse
    {
        $this->ensureIsApprovedProvider($provider);

        $reviews = $provider->receivedReviews()->visible()
            ->with('customer.media')
            ->latest()
            ->paginate(20);

        return $this->success([
            'reviews' => ReviewResource::collection($reviews)->resolve(),
            'current_page' => $reviews->currentPage(),
            'last_page' => $reviews->lastPage(),
            'total' => $reviews->total(),
        ]);
    }

    /**
     * Normal customers and providers that are not approved yet do not have a public profile.
     */
    private function ensureIsApprovedProvider(User $provider): void
    {
        abort_unless(
            $provider->is_provider && $provider->providerProfile?->status === ProviderProfile::STATUS_APPROVED,
            404,
        );
    }
}
