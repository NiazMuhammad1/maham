<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendPhoneOtpRequest;
use App\Http\Requests\Auth\VerifyPhoneOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SaudiPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Forgot Password" by phone -> OTP -> reset token -> "Update Password" -> "Password Changed".
 */
class ForgotPasswordController extends Controller
{
    private const int RESET_TOKEN_MINUTES = 15;

    public function __construct(private OtpService $otp) {}

    public function sendOtp(SendPhoneOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $user = $this->findUserByPhone($phone);

        $this->otp->send($phone, 'sms', OtpService::PURPOSE_FORGOT_PASSWORD, $user, 'phone');

        return $this->success([
            'phone_country_code' => SaudiPhone::COUNTRY_CODE,
            'phone' => $phone,
            'expires_in' => config('otp.expires_seconds'),
            'resend_in' => config('otp.resend_seconds'),
        ], __('messages.otp_sent_phone'));
    }

    public function verifyOtp(VerifyPhoneOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $user = $this->findUserByPhone($phone);

        $this->otp->verify($phone, OtpService::PURPOSE_FORGOT_PASSWORD, $request->validated('otp'));

        $resetToken = Str::random(64);
        Cache::put($this->cacheKey($resetToken), $user->id, now()->addMinutes(self::RESET_TOKEN_MINUTES));

        return $this->success([
            'reset_token' => $resetToken,
            'expires_in' => self::RESET_TOKEN_MINUTES * 60,
        ], __('messages.otp_verified'));
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $userId = Cache::pull($this->cacheKey($request->validated('reset_token')));
        $user = $userId ? User::find($userId) : null;

        if ($user === null) {
            throw ValidationException::withMessages(['reset_token' => __('messages.reset_token_invalid')]);
        }

        $user->update(['password' => $request->validated('password')]);
        $user->tokens()->delete();

        return $this->success(null, __('messages.password_changed'));
    }

    private function findUserByPhone(string $phone): User
    {
        $user = User::where('phone', $phone)->whereNotNull('phone_verified_at')->first();

        if ($user === null || $user->password === null) {
            throw ValidationException::withMessages(['phone' => __('messages.phone_not_registered')]);
        }

        return $user;
    }

    private function cacheKey(string $resetToken): string
    {
        return 'password_reset:'.hash('sha256', $resetToken);
    }
}
