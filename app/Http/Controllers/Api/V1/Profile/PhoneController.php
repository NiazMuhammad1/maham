<?php

namespace App\Http\Controllers\Api\V1\Profile;

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
 * Account Settings > Update Phone Number. The OTP goes to the NEW number; the account
 * phone only changes after that code is verified ("Phone Number Verified" popup).
 */
class PhoneController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function sendOtp(SendPhoneOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $this->ensurePhoneCanBeUsed($request, $phone);

        $this->otp->send($phone, 'sms', OtpService::PURPOSE_UPDATE_PHONE, $request->user(), 'phone');

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
        $this->ensurePhoneCanBeUsed($request, $phone);

        $this->otp->verify($phone, OtpService::PURPOSE_UPDATE_PHONE, $request->validated('otp'));

        $user = $request->user();
        $user->forceFill([
            'phone' => $phone,
            'phone_country_code' => SaudiPhone::COUNTRY_CODE,
            'phone_verified_at' => now(),
        ])->save();

        $user->load('defaultAddress.city', 'defaultAddress.district');

        return $this->success(['user' => new UserResource($user)], __('messages.phone_updated'));
    }

    /**
     * The new number must be different from the current one and not used by another account.
     */
    private function ensurePhoneCanBeUsed(Request $request, string $phone): void
    {
        $user = $request->user();

        if ($user->is_guest) {
            throw ValidationException::withMessages(['phone' => __('messages.guest_not_allowed')]);
        }

        if ($user->phone === $phone) {
            throw ValidationException::withMessages(['phone' => __('messages.phone_same_as_current')]);
        }

        $takenByAnotherUser = User::withTrashed()
            ->where('phone', $phone)
            ->whereKeyNot($user->id)
            ->exists();

        if ($takenByAnotherUser) {
            throw ValidationException::withMessages(['phone' => __('messages.phone_taken')]);
        }
    }
}
