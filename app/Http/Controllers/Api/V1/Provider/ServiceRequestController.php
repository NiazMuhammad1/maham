<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderServiceRequestResource;
use App\Http\Resources\ServiceResource;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Provider "Customer Requests" (Manage Requests): the Open / Offer Sent / Closed tabs with the
 * "All Services" filter, and Request Details (with the "Send Offer" and "Chat" buttons).
 */
class ServiceRequestController extends Controller
{
    /** The three tabs at the top of "Customer Requests". */
    private const array TABS = ['open', 'offer_sent', 'closed'];

    /** Everything ProviderServiceRequestResource shows (except the provider's own offer). */
    private const array RELATIONS = ['service.media', 'customer.media', 'city', 'district', 'media'];

    /**
     * ?tab=open (default) | offer_sent | closed and ?service_id=3 (the "All Services" dropdown, empty = all).
     *
     * - Open: requests in the provider's services and service area that they have not sent an offer on yet
     * - Offer Sent: the provider's offer is waiting for the customer's decision
     * - Closed: the customer accepted an offer (this one or another), cancelled the request, or the provider withdrew
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'tab' => ['nullable', Rule::in(self::TABS)],
            'service_id' => ['nullable', 'integer'],
        ]);

        $provider = $request->user();
        $this->ensureProviderCanUseDashboard($provider);
        $provider->load('serviceAreas');

        $query = ServiceRequest::query()
            ->with([...self::RELATIONS, 'offers' => $this->onlyOffersFrom($provider)])
            ->when($request->filled('service_id'), fn (Builder $query) => $query->where('service_id', $request->integer('service_id')))
            ->latest();

        $tab = $request->input('tab', 'open');

        if ($tab === 'open') {
            $query->matchingProvider($provider);
        } elseif ($tab === 'offer_sent') {
            $query->where('status', ServiceRequest::STATUS_OPEN)
                ->whereHas('offers', fn (Builder $offers) => $offers
                    ->where('provider_id', $provider->id)
                    ->where('status', Offer::STATUS_PENDING));
        } else {
            // Closed: the request is not open any more, or the provider's offer is not pending any more
            $query->offeredByProvider($provider)
                ->where(fn (Builder $closed) => $closed
                    ->where('status', '!=', ServiceRequest::STATUS_OPEN)
                    ->orWhereHas('offers', fn (Builder $offers) => $offers
                        ->where('provider_id', $provider->id)
                        ->where('status', '!=', Offer::STATUS_PENDING)));
        }

        $serviceRequests = $query->paginate(20);

        return $this->success([
            // Values for the "All Services" dropdown: the services the provider works in
            'services' => ServiceResource::collection($provider->providedServices()->with('media')->get())->resolve(),
            'requests' => ProviderServiceRequestResource::collection($serviceRequests)->resolve(),
            'current_page' => $serviceRequests->currentPage(),
            'last_page' => $serviceRequests->lastPage(),
            'total' => $serviceRequests->total(),
        ]);
    }

    /**
     * Request Details. `can_send_offer` shows / hides the "Send Offer" button.
     * Providers only see requests they can send an offer on or already sent one on (404 otherwise).
     */
    public function show(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $provider = $request->user();
        $this->ensureProviderCanUseDashboard($provider);
        abort_unless($serviceRequest->isVisibleToProvider($provider), 404);

        $serviceRequest->load([...self::RELATIONS, 'offers' => $this->onlyOffersFrom($provider)]);

        return $this->success([
            ...(new ProviderServiceRequestResource($serviceRequest))->resolve(),
            'can_send_offer' => $serviceRequest->canReceiveOfferFrom($provider),
        ]);
    }

    /**
     * Load only the provider's own offer, not the offers of other providers.
     */
    private function onlyOffersFrom(User $provider): Closure
    {
        return fn ($offers) => $offers->where('provider_id', $provider->id);
    }
}
