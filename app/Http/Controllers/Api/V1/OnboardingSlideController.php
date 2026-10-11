<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OnboardingSlideResource;
use App\Models\OnboardingSlide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingSlideController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['audience' => ['nullable', 'in:customer,provider']]);

        $slides = OnboardingSlide::active()
            ->where('audience', $request->input('audience', 'customer'))
            ->orderBy('sort_order')
            ->get();

        return $this->success(OnboardingSlideResource::collection($slides));
    }
}
