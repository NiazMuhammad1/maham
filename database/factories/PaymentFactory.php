<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'user_id' => fn (array $attributes) => Booking::find($attributes['booking_id'])->customer_id,
            'gateway' => 'fake',
            'method' => 'card',
            'transaction_id' => 'fake_'.Str::uuid(),
            'amount' => 250,
            'currency' => 'SAR',
            'status' => 'paid',
            'paid_at' => now(),
        ];
    }
}
