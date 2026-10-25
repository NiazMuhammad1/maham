<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

class OtpService
{
    public const string PURPOSE_REGISTER_PHONE = 'register_phone';

    public const string PURPOSE_VERIFY_EMAIL = 'verify_email';

    public const string PURPOSE_FORGOT_PASSWORD = 'forgot_password';

    public const string PURPOSE_UPDATE_PASSWORD = 'update_password';

    public const string PURPOSE_UPDATE_PHONE = 'update_phone';

    public function __construct(private SmsService $sms) {}

    /**
     * Generate a code and deliver it by SMS (national phone 5XXXXXXXX) or email.
     *
     * @param  string  $field  Request field that errors are reported against.
     *
     * @throws ValidationException when a code was sent less than `otp.resend_seconds` ago.
     */
    public function send(string $identifier, string $channel, string $purpose, ?User $user = null, string $field = 'otp'): OtpCode
    {
        $this->ensureCanResend($identifier, $purpose, $field);

        OtpCode::query()
            ->where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->delete();

        $code = $this->generateCode();

        $otp = OtpCode::create([
            'user_id' => $user?->id,
            'identifier' => $identifier,
            'channel' => $channel,
            'purpose' => $purpose,
            'code' => $code,
            'expires_at' => now()->addSeconds(config('otp.expires_seconds')),
        ]);

        try {
            if ($channel === 'sms') {
                $this->sms->send(SaudiPhone::international($identifier), __('messages.otp_sms', ['code' => $code]));
            } else {
                Mail::to($identifier)->send(new OtpMail($code));
            }
        } catch (Throwable $exception) {
            report($exception);
            $otp->delete();

            throw new ServiceUnavailableHttpException(null, __('messages.otp_send_failed'), $exception);
        }

        return $otp;
    }

    /**
     * Check a code and mark it used.
     *
     * @throws ValidationException when the code is missing, expired, wrong or out of attempts.
     */
    public function verify(string $identifier, string $purpose, string $code, string $field = 'otp'): OtpCode
    {
        $otp = OtpCode::query()
            ->where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if ($otp === null || $otp->isExpired()) {
            throw ValidationException::withMessages([$field => __('messages.otp_expired')]);
        }

        if ($otp->attempts >= config('otp.max_attempts')) {
            throw ValidationException::withMessages([$field => __('messages.otp_too_many_attempts')]);
        }

        if (! Hash::check($code, $otp->code)) {
            $otp->increment('attempts');

            throw ValidationException::withMessages([$field => __('messages.otp_invalid')]);
        }

        $otp->forceFill(['verified_at' => now()])->save();

        return $otp;
    }

    private function ensureCanResend(string $identifier, string $purpose, string $field): void
    {
        $lastSentAt = OtpCode::query()
            ->where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->latest('id')
            ->value('created_at');

        if ($lastSentAt === null) {
            return;
        }

        $secondsLeft = (int) ceil(config('otp.resend_seconds') - now()->diffInSeconds($lastSentAt, true));

        if ($secondsLeft > 0) {
            throw ValidationException::withMessages([
                $field => __('messages.otp_resend_wait', ['seconds' => $secondsLeft]),
            ])->status(429);
        }
    }

    private function generateCode(): string
    {
        $fixedCode = config('otp.fixed_code');

        if (filled($fixedCode) && ! app()->isProduction()) {
            return (string) $fixedCode;
        }

        $length = config('otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
