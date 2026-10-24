<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A chat thread between one customer and one provider ("Chat" tab).
 * There is only one thread per customer/provider pair, even if they have several bookings.
 */
#[Fillable([
    'customer_id',
    'provider_id',
    'service_request_id',
    'booking_id',
])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'customer_unread_count' => 'integer',
            'provider_unread_count' => 'integer',
        ];
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
     * The booking the chat was last opened from.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    /**
     * Threads the user is part of (as the customer or as the provider).
     *
     * @param  Builder<Conversation>  $query
     */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query->where('customer_id', $user->id)->orWhere('provider_id', $user->id));
    }

    public function hasParticipant(User $user): bool
    {
        return $this->customer_id === $user->id || $this->provider_id === $user->id;
    }

    /**
     * The person on the other side of the chat (shown at the top of the screen).
     */
    public function otherParticipant(User $user): User
    {
        return $this->customer_id === $user->id ? $this->provider : $this->customer;
    }

    /**
     * Unread messages for this user (highlighted row in the chat list).
     */
    public function unreadCountFor(User $user): int
    {
        return $this->customer_id === $user->id ? $this->customer_unread_count : $this->provider_unread_count;
    }

    /**
     * The user opened the thread: mark the other person's messages as read.
     */
    public function markAsReadBy(User $user): void
    {
        $this->messages()->where('sender_id', '!=', $user->id)->whereNull('read_at')->update(['read_at' => now()]);

        $column = $this->customer_id === $user->id ? 'customer_unread_count' : 'provider_unread_count';
        $this->forceFill([$column => 0])->save();
    }

    /**
     * Phone number for the call button. Only shown while there is an active booking between the two users.
     */
    public function callPhoneFor(User $user): ?string
    {
        $hasActiveBooking = Booking::query()
            ->where('customer_id', $this->customer_id)
            ->where('provider_id', $this->provider_id)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_IN_PROGRESS])
            ->exists();

        return $hasActiveBooking ? $this->otherParticipant($user)->fullPhone() : null;
    }
}
