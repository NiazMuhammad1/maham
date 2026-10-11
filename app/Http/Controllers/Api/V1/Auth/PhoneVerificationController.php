<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendPhoneOtpRequest;
use App\Http\Requests\Auth\VerifyPhoneOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SaudiPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Adds a verified phone to an account created with Google / Apple (next_step = verify_phone).
 */
class PhoneVerificationController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function send(SendPhoneOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $this->ensurePhoneIsAvailable($request, $phone);

        $this->otp->send($phone, 'sms', OtpService::PURPOSE_REGISTER_PHONE, $request->user(), 'phone');

        return $this->success([
            'phone_country_code' => SaudiPhone::COUNTRY_CODE,
            'phone' => $phone,
            'expires_in' => config('otp.expires_seconds'),
            'resend_in' => config('otp.resend_seconds'),
        ], __('messages.otp_sent_phone'));
    }

    public function verify(VerifyPhoneOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $this->ensurePhoneIsAvailable($request, $phone);

        $this->otp->verify($phone, OtpService::PURPOSE_REGISTER_PHONE, $request->validated('otp'));

        $user = $request->user();
        $user->forceFill([
            'phone' => $phone,
            'phone_country_code' => SaudiPhone::COUNTRY_CODE,
            'phone_verified_at' => now(),
        ])->save();

        return $this->success([
            'next_step' => $user->nextRegistrationStep(),
            'user' => new UserResource($user),
        ], __('messages.phone_verified'));
    }

    private function ensurePhoneIsAvailable(Request $request, string $phone): void
    {
        if ($request->user()->is_guest) {
            throw ValidationException::withMessages(['phone' => __('messages.guest_not_allowed')]);
        }

        $takenByAnotherUser = User::withTrashed()
            ->where('phone', $phone)
            ->whereKeyNot($request->user()->id)
            ->exists();

        if ($takenByAnotherUser) {
            throw ValidationException::withMessages(['phone' => __('messages.phone_already_registered')]);
        }
    }
}
