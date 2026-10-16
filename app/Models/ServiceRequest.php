<?php

namespace App\Models;

use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A job the customer posts from "Request Service" (address, date & time, description, budget, photos).
 * Providers send offers on it; the customer sees it under "My Requests".
 */
#[Fillable([
    'service_id',
    'description',
    'budget',
    'currency',
    'preferred_date',
    'preferred_time',
    'flexibility',
    'address_id',
    'city_id',
    'district_id',
    'street_address',
    'postal_code',
    'latitude',
    'longitude',
])]
class ServiceRequest extends Model implements HasMedia
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /** Values for the "Flexibility" dropdown. */
    public const array FLEXIBILITIES = ['fixed', 'flexible', 'very_flexible'];

    /** The "Add photos (optional)" screen has 6 boxes. */
    public const int MAX_IMAGES = 6;

    public const string STATUS_OPEN = 'open';

    public const string STATUS_ACCEPTED = 'accepted';

    public const string STATUS_CANCELLED = 'cancelled';

    public const string STATUS_EXPIRED = 'expired';

    /**
     * Same defaults as the table, so a new request has them before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_OPEN,
        'currency' => 'SAR',
        'offers_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'preferred_date' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Give every new request a short reference the customer can read, e.g. REQ-482913.
     */
    protected static function booted(): void
    {
        static::creating(function (ServiceRequest $serviceRequest) {
            do {
                $reference = 'REQ-'.random_int(100000, 999999);
            } while (static::withTrashed()->where('reference', $reference)->exists());

            $serviceRequest->reference = $reference;
        });
    }

    /**
     * Photos of the problem (up to 6).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
