<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The `type` column holds a short key (offer_received, payment_successful ...) and `data.params`
 * holds the values, so the title and body are translated into the reader's language here.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $params = $this->data['params'] ?? [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => __("notifications.{$this->type}.title", $params),
            'body' => __("notifications.{$this->type}.body", $params),
            'service_request_id' => $this->data['service_request_id'] ?? null,
            'booking_id' => $this->data['booking_id'] ?? null,
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
