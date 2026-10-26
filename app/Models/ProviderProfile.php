<?php

namespace App\Models;

use Database\Factories\ProviderProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Business profile of a service provider (tagline, "Skills and Experience", rating stats).
 * The provider is a normal user with `is_provider = true`.
 *
 * It is also the "Become a Service Provider" application: created as a draft on the first step,
 * sent for admin review on "Submit details" (`submitted_at`), then approved or rejected by an admin.
 */
#[Fillable([
    'business_name',
    'tagline',
    'skills_experience',
    'website',
    'status',
])]
class ProviderProfile extends Model implements HasMedia
{
    /** @use HasFactory<ProviderProfileFactory> */
    use HasFactory, InteractsWithMedia;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_REJECTED = 'rejected';

    public const string STATUS_SUSPENDED = 'suspended';

    protected function casts(): array
    {
        return [
            'rating_avg' => 'float',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Business logo or professional headshot (one file, a new upload replaces the old one).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logoUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo') ?: null;
    }

    /**
     * The applicant can still change the application while it is a draft or after it was rejected.
     * Once submitted (waiting for review) or approved, the steps are locked.
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_REJECTED
            || ($this->status === self::STATUS_PENDING && $this->submitted_at === null);
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
