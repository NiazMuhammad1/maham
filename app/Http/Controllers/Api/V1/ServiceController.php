<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

/**
 * "All Services" grid and the "Service Details" screen.
 */
class ServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $services = Service::active()->with('media')->orderBy('sort_order')->get();

        return $this->success(ServiceResource::collection($services));
    }

    public function show(Service $service): JsonResponse
    {
        abort_unless($service->is_active, 404);

        $service->load(['subServices' => fn ($query) => $query->active()->orderBy('sort_order')]);

        return $this->success(new ServiceResource($service));
    }
}
