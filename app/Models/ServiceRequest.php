<?php

namespace App\Models;

use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     * Offers sent by providers ("Offers Received").
     *
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * True while the customer can still cancel the request or accept an offer on it.
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Open requests a provider can send an offer on ("Recent Requests" on the provider Home):
     * in one of their services, inside one of their service areas, not in the past,
     * not their own request, and they have not sent an offer on it yet.
     *
     * @param  Builder<ServiceRequest>  $query
     */
    public function scopeMatchingProvider(Builder $query, User $provider): void
    {
        $query->where('status', self::STATUS_OPEN)
            ->where('customer_id', '!=', $provider->id)
            ->whereDate('preferred_date', '>=', today())
            ->whereIn('service_id', $provider->providedServices()->select('services.id'))
            ->whereDoesntHave('offers', fn (Builder $offers) => $offers->where('provider_id', $provider->id))
            ->where(function (Builder $areaQuery) use ($provider) {
                // No service area yet = no matching requests
                $areaQuery->whereRaw('1 = 0');

                // A service area without a district covers the whole city
                foreach ($provider->serviceAreas as $area) {
                    $areaQuery->orWhere(function (Builder $cityQuery) use ($area) {
                        $cityQuery->where('city_id', $area->city_id)
                            ->when($area->district_id, fn (Builder $q) => $q->where('district_id', $area->district_id));
                    });
                }
            });
    }

    /**
     * Requests the provider has sent an offer on (pending, accepted, rejected or withdrawn).
     *
     * @param  Builder<ServiceRequest>  $query
     */
    public function scopeOfferedByProvider(Builder $query, User $provider): void
    {
        $query->whereHas('offers', fn (Builder $offers) => $offers->where('provider_id', $provider->id));
    }

    /**
     * True when the provider can still send an offer on this request (it is on their "Open" tab).
     */
    public function canReceiveOfferFrom(User $provider): bool
    {
        return static::query()->matchingProvider($provider)->whereKey($this->id)->exists();
    }

    /**
     * A provider can open a request (Request Details, Chat) when they can send an offer on it
     * or when they already sent one. Other requests are hidden from them.
     */
    public function isVisibleToProvider(User $provider): bool
    {
        return $this->offers()->where('provider_id', $provider->id)->exists()
            || $this->canReceiveOfferFrom($provider);
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
