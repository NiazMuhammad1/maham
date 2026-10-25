<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SaudiPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Account Settings > Update Password, confirmed with an OTP sent to the user's phone:
 *
 * 1. send-otp: check current + new password, then send the code ("Sending OTP" popup).
 * 2. update:   same fields + otp -> password saved ("Password Changed" popup).
 */
class PasswordController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function sendOtp(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureUserHasPhone($user);

        $this->otp->send($user->phone, 'sms', OtpService::PURPOSE_UPDATE_PASSWORD, $user);

        return $this->success([
            'phone_country_code' => $user->phone_country_code,
            'phone' => $user->phone,
            'expires_in' => config('otp.expires_seconds'),
            'resend_in' => config('otp.resend_seconds'),
        ], __('messages.otp_sent_phone'));
    }

    public function update(ChangePasswordRequest $request): JsonResponse
    {
        $request->validate(['otp' => ['required', 'string', 'digits:'.config('otp.length')]]);

        $user = $request->user();
        $this->ensureUserHasPhone($user);

        $this->otp->verify($user->phone, OtpService::PURPOSE_UPDATE_PASSWORD, $request->input('otp'));

        $user->update(['password' => $request->validated('password')]);

        // Log out every other device; the current one stays logged in.
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return $this->success(null, __('messages.password_changed'));
    }

    private function ensureUserHasPhone(User $user): void
    {
        if (! SaudiPhone::isValid($user->phone) || $user->phone_verified_at === null) {
            throw ValidationException::withMessages(['phone' => __('messages.phone_missing')]);
        }
    }
}
