<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'phone_country_code' => $this->phone_country_code,
            'phone' => $this->phone,
            'phone_verified' => $this->phone_verified_at !== null,
            'national_id' => $this->national_id,
            'avatar_url' => $this->avatarUrl(),
            'language' => $this->language,
            'push_notifications' => $this->push_notifications,
            'email_notifications' => $this->email_notifications,
            'is_guest' => $this->is_guest,
            'is_provider' => $this->is_provider,
            'active_mode' => $this->active_mode,
            'has_password' => $this->password !== null,
            'registration_completed' => $this->registration_completed_at !== null,
            'next_step' => $this->nextRegistrationStep(),
            'default_address' => new AddressResource($this->whenLoaded('defaultAddress')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
