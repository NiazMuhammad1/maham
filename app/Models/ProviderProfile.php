<?php

namespace App\Models;

use Database\Factories\ProviderProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Business profile of a service provider (tagline, "Skills and Experience", rating stats).
 * The provider is a normal user with `is_provider = true`.
 */
#[Fillable([
    'business_name',
    'tagline',
    'skills_experience',
    'website',
    'status',
])]
class ProviderProfile extends Model
{
    /** @use HasFactory<ProviderProfileFactory> */
    use HasFactory;

    public const string STATUS_APPROVED = 'approved';

    protected function casts(): array
    {
        return [
            'rating_avg' => 'float',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Re-calculate "4.9 (312 reviews)" from the visible reviews. Call it after a review is added or hidden.
     */
    public function refreshRatingStats(): void
    {
        $reviews = Review::query()->where('provider_id', $this->user_id)->where('is_visible', true);

        $this->forceFill([
            'rating_avg' => round((float) $reviews->avg('rating'), 2),
            'reviews_count' => $reviews->count(),
        ])->save();
    }
}
