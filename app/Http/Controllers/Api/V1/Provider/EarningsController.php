<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderBookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Earnings screen: Daily / Weekly / Monthly / Yearly / All tabs, the green total card
 * ("Earnings SAR 265.00", "Completed Bookings 85") and the completed bookings of that period.
 */
class EarningsController extends Controller
{
    /** The tabs at the top of the screen. */
    private const array PERIODS = ['daily', 'weekly', 'monthly', 'yearly', 'all'];

    /**
     * ?period=weekly (default) &page=1. Bookings are listed newest completed first, 20 per page.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['period' => ['nullable', Rule::in(self::PERIODS)]]);

        $provider = $request->user();
        $this->ensureProviderCanUseDashboard($provider);

        $period = $request->input('period', 'weekly');
        $from = $this->periodStart($period);

        $completed = $provider->providerBookings()
            ->where('status', Booking::STATUS_COMPLETED)
            ->when($from, fn ($query) => $query->where('completed_at', '>=', $from));

        $bookings = (clone $completed)
            ->with(['service.media', 'customer.media', 'city', 'district'])
            ->latest('completed_at')
            ->paginate(20);

        return $this->success([
            'period' => $period,
            'periods' => collect(self::PERIODS)->map(fn (string $value) => [
                'value' => $value,
                'label' => __("messages.earning_periods.{$value}"),
            ]),
            'earnings' => (float) (clone $completed)->sum('provider_earning'),
            'completed_bookings' => $bookings->total(),
            'currency' => 'SAR',
            'bookings' => ProviderBookingResource::collection($bookings)->resolve(),
            'current_page' => $bookings->currentPage(),
            'last_page' => $bookings->lastPage(),
            'total' => $bookings->total(),
        ]);
    }

    /**
     * Daily = today, Weekly = this week (Saudi week starts on Sunday), Monthly = this month,
     * Yearly = this year, All = no start date.
     */
    private function periodStart(string $period): ?Carbon
    {
        return match ($period) {
            'daily' => now()->startOfDay(),
            'weekly' => now()->startOfWeek(Carbon::SUNDAY),
            'monthly' => now()->startOfMonth(),
            'yearly' => now()->startOfYear(),
            default => null,
        };
    }
}
