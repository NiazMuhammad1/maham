<?php

namespace App\Http\Resources;

use App\Models\OnboardingSlide;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin OnboardingSlide
 */
class OnboardingSlideResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->localized('title'),
            'description' => $this->localized('description'),
            'image_url' => Storage::disk('public')->url($this->image),
            'sort_order' => $this->sort_order,
        ];
    }
}
