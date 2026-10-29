<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment attempt for a booking (card or Apple Pay through the payment gateway).
 */
#[Fillable([
    'user_id',
    'gateway',
    'method',
    'transaction_id',
    'amount',
    'currency',
    'status',
    'gateway_response',
    'paid_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /** Methods shown on the "Payments" sheet. */
    public const array METHODS = ['card', 'apple_pay', 'tabby', 'tamara'];

    /** "Buy now, pay later" methods: paid on the gateway's own checkout page (opened in a WebView). */
    public const array INSTALLMENT_METHODS = ['tabby', 'tamara'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
