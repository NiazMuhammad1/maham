<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'icon_url' => $this->getFirstMediaUrl('icon') ?: null,
            'icon_bg_color' => $this->icon_bg_color,
            'image_url' => $this->getFirstMediaUrl('image') ?: null,
            'sub_services' => SubServiceResource::collection($this->whenLoaded('subServices')),
        ];
    }
}
