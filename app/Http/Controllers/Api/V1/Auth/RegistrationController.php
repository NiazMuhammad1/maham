<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AddressRequest;
use App\Http\Requests\Auth\CompleteRegistrationRequest;
use App\Http\Requests\Auth\CredentialsRequest;
use App\Http\Requests\Auth\PersonalDetailsRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Sign-up steps 2-6, called with the token returned by the phone OTP verification.
 */
class RegistrationController extends Controller
{
    public function __construct(private OtpService $otp) {}

    /**
     * "Tell us about yourself": full name, username, National ID / Iqama.
     */
    public function personalDetails(PersonalDetailsRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->safe()->only(['name', 'username', 'national_id']));

        return $this->userResponse($user, __('messages.details_saved'));
    }

    /**
     * "Secure your account": email + password. Sends the email verification code.
     */
    public function credentials(CredentialsRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['email', 'password']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->email_verified_at === null) {
            try {
                $this->otp->send($user->email, 'email', OtpService::PURPOSE_VERIFY_EMAIL, $user);
            } catch (ValidationException) {
                // A code was sent seconds ago (user tapped Next twice); the existing code is still valid.
            }
        }

        $message = $user->email_verified_at === null ? __('messages.otp_sent_email') : __('messages.details_saved');

        return $this->userResponse($user, $message);
    }

    /**
     * "Where should we find you?": city, district, street, postal code.
     */
    public function address(AddressRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->addresses()->updateOrCreate(
            ['is_default' => true],
            [...$request->validated(), 'label' => 'Home'],
        );

        return $this->userResponse($user, __('messages.details_saved'));
    }

    /**
     * "Add a profile photo": send `avatar` for "Submit details", or nothing for "Skip".
     */
    public function complete(CompleteRegistrationRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->nextRegistrationStep() !== User::STEP_PROFILE_IMAGE) {
            throw ValidationException::withMessages([
                'next_step' => __('messages.registration_incomplete', ['step' => $user->nextRegistrationStep()]),
            ]);
        }

        if ($request->hasFile('avatar')) {
            $user->avatar && Storage::disk('public')->delete($user->avatar);
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->registration_completed_at = now();
        $user->save();

        return $this->userResponse($user, __('messages.registration_completed'));
    }

    private function userResponse(User $user, string $message): JsonResponse
    {
        $user->load('defaultAddress.city', 'defaultAddress.district');

        return $this->success([
            'next_step' => $user->nextRegistrationStep(),
            'user' => new UserResource($user),
        ], $message);
    }
}
