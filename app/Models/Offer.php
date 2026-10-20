<?php

namespace App\Models;

use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A provider's price for a customer's service request ("Offers Received").
 *
 * The provider either agrees with the customer's date and time (schedule_type = customer_time)
 * or proposes another one (schedule_type = proposed, with proposed_date / proposed_time).
 */
#[Fillable([
    'provider_id',
    'price',
    'currency',
    'description',
    'schedule_type',
    'proposed_date',
    'proposed_time',
])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_ACCEPTED = 'accepted';

    public const string STATUS_REJECTED = 'rejected';

    public const string STATUS_WITHDRAWN = 'withdrawn';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'currency' => 'SAR',
        'schedule_type' => 'customer_time',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'proposed_date' => 'date',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * @return HasOne<Booking, $this>
     */
    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    /**
     * The date the work will happen: the provider's proposed date, or the customer's preferred date.
     */
    public function scheduledDate(): Carbon
    {
        return $this->schedule_type === 'proposed' && $this->proposed_date
            ? $this->proposed_date
            : $this->serviceRequest->preferred_date;
    }

    /**
     * The time the work will happen ("H:i:s" as stored in the database), or null when not set.
     */
    public function scheduledTime(): ?string
    {
        return $this->schedule_type === 'proposed' && $this->proposed_time
            ? $this->proposed_time
            : $this->serviceRequest->preferred_time;
    }
}
