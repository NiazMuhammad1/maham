<?php

namespace App\Http\Resources;

use App\Models\SubService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubService
 */
class SubServiceResource extends JsonResource
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
            'name' => $this->localized('name'),
            'description' => $this->localized('description'),
        ];
    }
}
