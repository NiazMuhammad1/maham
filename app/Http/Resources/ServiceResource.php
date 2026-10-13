<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
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
            'slug' => $this->slug,
            'name' => $this->localized('name'),
            'description' => $this->localized('description'),
            'icon_url' => $this->icon ? Storage::disk('public')->url($this->icon) : null,
            'icon_bg_color' => $this->icon_bg_color,
            'image_url' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'sub_services' => SubServiceResource::collection($this->whenLoaded('subServices')),
        ];
    }
}
