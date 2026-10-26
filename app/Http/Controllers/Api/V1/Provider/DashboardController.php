<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderBookingResource;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Booking;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Provider Home (Dashboard): header and side menu, "Your performance" for a month,
 * "Upcoming Bookings" and "Recent Requests" (3 each, "View All" opens the full lists).
 */
class DashboardController extends Controller
{
    /** How many items the two Home lists show. */
    private const int LIST_LIMIT = 3;

    /**
     * ?month=2026-08 (default: this month) changes the "Your performance" numbers.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $provider = $request->user();
        $this->ensureProviderCanUseDashboard($provider);

        $locale = app()->getLocale();
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth()
            : now()->startOfMonth();

        // Work finished in the chosen month
        $completedInMonth = $provider->providerBookings()
            ->where('status', Booking::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$month, $month->copy()->endOfMonth()]);

        // Average stars of the reviews received in the chosen month (null = no reviews that month)
        $monthRating = Review::query()->visible()
            ->where('provider_id', $provider->id)
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->avg('rating');

        $upcomingBookings = $provider->providerBookings()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereDate('scheduled_date', '>=', today())
            ->with(['service.media', 'customer.media', 'city', 'district'])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->limit(self::LIST_LIMIT)
            ->get();

        $provider->load('serviceAreas');
        $recentRequests = ServiceRequest::query()
            ->matchingProvider($provider)
            ->with(['service.media', 'city', 'district', 'media'])
            ->latest()
            ->limit(self::LIST_LIMIT)
            ->get();

        return $this->success([
            // Header ("Mohammed Al-Rashidi" + bell) and side menu card
            'provider' => [
                'id' => $provider->id,
                'name' => $provider->name,
                'username' => $provider->username,
                'avatar_url' => $provider->avatarUrl(),
            ],
            'unread_notifications_count' => $provider->notificationsFor(User::MODE_PROVIDER)->whereNull('read_at')->count(),

            'performance' => [
                'month' => $month->format('Y-m'),
                'month_label' => $month->locale($locale)->translatedFormat('F Y'),
                'earnings' => (float) (clone $completedInMonth)->sum('provider_earning'),
                'completed_bookings' => (clone $completedInMonth)->count(),
                'rating_avg' => $monthRating !== null ? round((float) $monthRating, 1) : null,
                'currency' => 'SAR',
            ],

            // Values for the month dropdown: this month and the 11 before it
            'months' => collect(range(0, 11))->map(function (int $monthsAgo) use ($locale) {
                $option = now()->startOfMonth()->subMonths($monthsAgo);

                return ['value' => $option->format('Y-m'), 'label' => $option->locale($locale)->translatedFormat('F Y')];
            }),

            'upcoming_bookings' => ProviderBookingResource::collection($upcomingBookings)->resolve(),
            'recent_requests' => ServiceRequestResource::collection($recentRequests)->resolve(),
        ]);
    }
}
