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

    /** "Agree with Customer's Time" or "Propose a Different Date & Time" on Send Offer. */
    public const array SCHEDULE_TYPES = ['customer_time', 'proposed'];

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
     * What the customer pays and what the provider receives for this offer.
     * Example (5% + 5%): price 200 -> processing fee 10, customer pays 210; commission 10, provider receives 190.
     *
     * @return array{service_price: float, processing_fee_percent: float, processing_fee: float, total_amount: float, provider_commission: float, provider_earning: float}
     */
    public function priceBreakdown(): array
    {
        $price = (float) $this->price;
        $feePercent = (float) Setting::valueOf('customer_processing_fee_percent', 5);
        $commissionPercent = (float) Setting::valueOf('provider_commission_percent', 5);

        $processingFee = round($price * $feePercent / 100, 2);
        $commission = round($price * $commissionPercent / 100, 2);

        return [
            'service_price' => $price,
            'processing_fee_percent' => $feePercent,
            'processing_fee' => $processingFee,
            'total_amount' => round($price + $processingFee, 2),
            'provider_commission' => $commission,
            'provider_earning' => round($price - $commission, 2),
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
