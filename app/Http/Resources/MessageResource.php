<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One chat bubble: "Me" or the other person's name and photo, the date and the text or photo.
 * A `system` message is the safety warning (show it in the middle, without a sender).
 *
 * Load `sender.media` and `media` before using it.
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSystem = $this->type === Message::TYPE_SYSTEM;
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'type' => $this->type,
            // The warning text is translated when read, so it follows the reader's language
            'body' => $isSystem ? __('messages.chat_safety_warning') : $this->body,
            'image_url' => $this->type === Message::TYPE_IMAGE ? $this->imageUrl() : null,
            // true -> show "Me"
            'is_mine' => ! $isSystem && $this->sender_id === $request->user()->id,
            'sender' => $isSystem ? null : [
                'id' => $this->sender?->id,
                'name' => $this->sender?->name,
                'avatar_url' => $this->sender?->avatarUrl(),
            ],
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            // e.g. "14 Aug, 2026" and "10:30 AM"
            'created_at_label' => $this->created_at?->locale($locale)->translatedFormat('d M, Y'),
            'time_label' => $this->created_at?->locale($locale)->translatedFormat('h:i A'),
        ];
    }
}
