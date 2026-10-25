<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Notification Settings toggles. Send one or both switches.
 */
class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->is_guest;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'push_notifications' => ['required_without:email_notifications', 'boolean'],
            'email_notifications' => ['required_without:push_notifications', 'boolean'],
        ];
    }
}
