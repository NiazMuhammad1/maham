<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequestRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Offer;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer "Request Service" flow:
 *
 * Service Details -> "Request service" -> 1. Add Address -> 2. Select Date and Time
 * -> 3. What do you need done? -> 4. Add photos -> Confirm Request -> "Submit Request"
 * -> "Request Submitted" popup -> My Requests.
 *
 * Steps 1-4 and the Confirm screen are kept on the phone; only "Submit Request" calls the server.
 */
class ServiceRequestController extends Controller
{
    /**
     * Values for the dropdowns on the request screens (Flexibility list, currency, max photos).
     */
    public function options(): JsonResponse
    {
        $flexibilities = collect(ServiceRequest::FLEXIBILITIES)->map(fn (string $value) => [
            'value' => $value,
            'label' => __("messages.flexibility.{$value}"),
        ]);

        return $this->success([
            'flexibilities' => $flexibilities,
            'currency' => 'SAR',
            'max_images' => ServiceRequest::MAX_IMAGES,
            'max_image_size_kb' => 5120,
            'min_date' => today()->toDateString(),
        ]);
    }

    /**
     * My Requests list. ?tab=open (default) shows open requests, ?tab=closed shows accepted / cancelled / expired.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['tab' => ['nullable', 'in:open,closed']]);

        $query = $request->user()->serviceRequests()
            ->with(['service.media', 'city', 'district', 'media'])
            ->latest();

        if ($request->input('tab', 'open') === 'open') {
            $query->where('status', ServiceRequest::STATUS_OPEN);
        } else {
            $query->where('status', '!=', ServiceRequest::STATUS_OPEN);
        }

        $serviceRequests = $query->paginate(20);

        return $this->success([
            'requests' => ServiceRequestResource::collection($serviceRequests)->resolve(),
            'current_page' => $serviceRequests->currentPage(),
            'last_page' => $serviceRequests->lastPage(),
            'total' => $serviceRequests->total(),
        ]);
    }

    /**
     * "Submit Request": save the request and its photos, then show the "Request Submitted" popup.
     */
    public function store(StoreServiceRequestRequest $request): JsonResponse
    {
        // Save every field except the photos (customer_id is filled by the relation)
        $serviceRequest = $request->user()->serviceRequests()->create($request->safe()->except('images'));

        // Save each photo into the "images" media collection (storage/app/public/{media id}/...)
        foreach ($request->file('images', []) as $image) {
            $serviceRequest->addMedia($image)->toMediaCollection('images');
        }

        $serviceRequest->load(['service.media', 'city', 'district', 'media']);

        return $this->success(new ServiceRequestResource($serviceRequest), __('messages.request_submitted'), 201);
    }

    /**
     * One request (opened from My Requests). Customers can only see their own requests.
     */
    public function show(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        abort_unless($serviceRequest->customer_id === $request->user()->id, 404);

        $serviceRequest->load(['service.media', 'city', 'district', 'media']);

        return $this->success(new ServiceRequestResource($serviceRequest));
    }

    /**
     * "Cancel Request" (My Requests card or Request Details) after the "Are you sure?" popup.
     * Only open requests can be cancelled; their pending offers are rejected and it moves to the Closed tab.
     */
    public function cancel(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        abort_unless($serviceRequest->customer_id === $request->user()->id, 404);

        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        if (! $serviceRequest->isOpen()) {
            throw ValidationException::withMessages(['service_request' => __('messages.request_cannot_be_cancelled')]);
        }

        DB::transaction(function () use ($request, $serviceRequest) {
            $serviceRequest->forceFill([
                'status' => ServiceRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $request->input('reason'),
            ])->save();

            $serviceRequest->offers()->where('status', Offer::STATUS_PENDING)->update(['status' => Offer::STATUS_REJECTED]);
        });

        $serviceRequest->load(['service.media', 'city', 'district', 'media']);

        return $this->success(new ServiceRequestResource($serviceRequest), __('messages.request_cancelled'));
    }
}
