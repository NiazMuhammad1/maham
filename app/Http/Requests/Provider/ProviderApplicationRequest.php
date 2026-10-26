<?php

namespace App\Http\Requests\Provider;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for the "Become a Service Provider" steps: only fully registered (non-guest) users can apply.
 */
abstract class ProviderApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ! $user->is_guest && $user->registration_completed_at !== null;
    }

    /**
     * Tell the app which sign-up screen is still missing (e.g. "address"), so it can open it.
     */
    protected function failedAuthorization(): void
    {
        $user = $this->user();

        if ($user->is_guest) {
            throw new AuthorizationException(__('messages.guest_not_allowed'));
        }

        throw new AuthorizationException(__('messages.provider_application_not_allowed', [
            'step' => $user->nextRegistrationStep(),
        ]));
    }
}
