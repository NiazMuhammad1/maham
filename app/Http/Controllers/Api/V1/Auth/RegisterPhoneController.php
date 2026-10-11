<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendPhoneOtpRequest;
use App\Http\Requests\Auth\VerifyPhoneOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SaudiPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Sign-up step 1: "What's your phone number?" and the OTP sheet.
 */
class RegisterPhoneController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function sendOtp(SendPhoneOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $this->ensurePhoneIsNotRegistered($phone);

        $this->otp->send($phone, 'sms', OtpService::PURPOSE_REGISTER_PHONE, field: 'phone');

        return $this->success($this->otpPayload($phone), __('messages.otp_sent_phone'));
    }

    /**
     * Creates the account (or resumes an unfinished sign-up) and returns a token for the next steps.
     */
    public function verifyOtp(VerifyPhoneOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $this->ensurePhoneIsNotRegistered($phone);

        $this->otp->verify($phone, OtpService::PURPOSE_REGISTER_PHONE, $request->validated('otp'));

        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['phone_country_code' => SaudiPhone::COUNTRY_CODE, 'language' => app()->getLocale()],
        );
        $user->forceFill(['phone_verified_at' => now()])->save();

        return $this->success($this->issueToken($user, $request), __('messages.phone_verified'));
    }

    private function ensurePhoneIsNotRegistered(string $phone): void
    {
        $isRegistered = User::withTrashed()
            ->where('phone', $phone)
            ->whereNotNull('registration_completed_at')
            ->exists();

        if ($isRegistered) {
            throw ValidationException::withMessages(['phone' => __('messages.phone_already_registered')]);
        }
    }

    /**
     * @return array{phone_country_code: string, phone: string, expires_in: int, resend_in: int}
     */
    private function otpPayload(string $phone): array
    {
        return [
            'phone_country_code' => SaudiPhone::COUNTRY_CODE,
            'phone' => $phone,
            'expires_in' => config('otp.expires_seconds'),
            'resend_in' => config('otp.resend_seconds'),
        ];
    }
}
