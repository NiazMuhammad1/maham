<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Home screen: address in the header, notification bell dot, "Become a service provider" card and Popular Services.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $address = $user->defaultAddress()->with('city', 'district')->first();

        $popularServices = Service::active()->where('is_popular', true)->orderBy('sort_order')->get();

        return $this->success([
            'address' => $address ? new AddressResource($address) : null,
            'unread_notifications_count' => $user->unreadNotifications()->count(),
            'show_become_provider' => ! $user->is_provider,
            'popular_services' => ServiceResource::collection($popularServices),
        ]);
    }
}
