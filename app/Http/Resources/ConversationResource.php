<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row on the "Chat" list (and the header of a chat): the other person, the last message and the unread count.
 *
 * Load `customer.media`, `provider.media` and `lastMessage` before using it.
 *
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $otherUser = $this->otherParticipant($user);
        $lastMessage = $this->lastMessage;

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,

            // Name, @username and photo at the top of the chat
            'participant' => [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
                'username' => $otherUser->username,
                'avatar_url' => $otherUser->avatarUrl(),
            ],

            // Grey preview text under the name (a photo shows as "Photo")
            'last_message' => $lastMessage ? [
                'id' => $lastMessage->id,
                'type' => $lastMessage->type,
                'body' => match ($lastMessage->type) {
                    Message::TYPE_IMAGE => $lastMessage->body ?: __('messages.chat_photo'),
                    Message::TYPE_SYSTEM => __('messages.chat_safety_warning'),
                    default => $lastMessage->body,
                },
                'is_mine' => $lastMessage->sender_id === $user->id,
            ] : null,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_message_at_label' => $this->last_message_at?->locale(app()->getLocale())->translatedFormat('d M, Y'),

            // More than 0 -> highlight the row
            'unread_count' => $this->unreadCountFor($user),
        ];
    }
}
