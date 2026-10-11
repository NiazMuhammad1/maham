<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for the sign-up steps that run after the phone number is verified.
 */
abstract class RegistrationStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ! $user->is_guest
            && $user->phone_verified_at !== null
            && $user->registration_completed_at === null;
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('messages.registration_step_not_allowed'));
    }
}
