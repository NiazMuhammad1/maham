<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Created when the customer accepts an offer and pays ("Booking Confirmed").
 * Keeps a copy of the date, address and price so later changes to the request or offer do not affect it.
 */
#[Fillable([
    'service_request_id',
    'offer_id',
    'customer_id',
    'provider_id',
    'service_id',
    'scheduled_date',
    'scheduled_time',
    'city_id',
    'district_id',
    'street_address',
    'postal_code',
    'latitude',
    'longitude',
    'service_price',
    'vat_amount',
    'platform_fee',
    'total_amount',
    'provider_earning',
    'currency',
    'status',
    'payment_status',
    'confirmed_at',
    'started_at',
    'completed_at',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    public const string STATUS_CONFIRMED = 'confirmed';

    public const string STATUS_IN_PROGRESS = 'in_progress';

    public const string STATUS_COMPLETED = 'completed';

    /** The 3 tabs on "My Bookings". */
    public const array TABS = [self::STATUS_CONFIRMED, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'service_price' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'provider_earning' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Give every booking a reference (BK-482913) and a receipt number (RCPT-20261019-482913).
     */
    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            do {
                $number = random_int(100000, 999999);
            } while (static::where('reference', 'BK-'.$number)->exists());

            $booking->reference = 'BK-'.$number;
            $booking->receipt_number = 'RCPT-'.now()->format('Ymd').'-'.$number;
        });
    }

    /**
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
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

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The successful payment (shown on the receipt).
     *
     * @return HasOne<Payment, $this>
     */
    public function paidPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', 'paid')->latestOfMany();
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * "Rate the Service Provider" is shown once the work is completed and until the customer rates it.
     * Load `review` before calling it.
     */
    public function canBeReviewed(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->review === null;
    }
}
