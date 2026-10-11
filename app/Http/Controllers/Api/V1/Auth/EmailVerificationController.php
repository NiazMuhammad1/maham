<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Verify Email" sheet: resend and verify the code sent to the account email.
 */
class EmailVerificationController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function send(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureEmailNeedsVerification($request);

        $this->otp->send($user->email, 'email', OtpService::PURPOSE_VERIFY_EMAIL, $user);

        return $this->success([
            'email' => $user->email,
            'expires_in' => config('otp.expires_seconds'),
            'resend_in' => config('otp.resend_seconds'),
        ], __('messages.otp_sent_email'));
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate(['otp' => ['required', 'string', 'digits:'.config('otp.length')]]);
        $user = $request->user();
        $this->ensureEmailNeedsVerification($request);

        $this->otp->verify($user->email, OtpService::PURPOSE_VERIFY_EMAIL, $request->input('otp'));
        $user->forceFill(['email_verified_at' => now()])->save();

        return $this->success([
            'next_step' => $user->nextRegistrationStep(),
            'user' => new UserResource($user),
        ], __('messages.email_verified'));
    }

    private function ensureEmailNeedsVerification(Request $request): void
    {
        $user = $request->user();

        if (blank($user->email)) {
            throw ValidationException::withMessages(['email' => __('messages.email_missing')]);
        }

        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages(['email' => __('messages.email_already_verified')]);
        }
    }
}
