<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One review on "Provider Details" / "Customers Reviews": customer photo and name, date, stars and comment.
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
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
            'rating' => $this->rating,
            'comment' => $this->comment,
            'customer' => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
                'avatar_url' => $this->customer?->avatarUrl(),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            // e.g. "14 Aug, 2026" / "14 أغسطس, 2026"
            'created_at_label' => $this->created_at?->locale(app()->getLocale())->translatedFormat('d M, Y'),
        ];
    }
}
