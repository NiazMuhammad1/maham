<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends SMS messages through the gateway chosen by SMS_DRIVER in .env:
 * "log" (local development), "taqnyat" or "unifonic".
 */
class SmsService
{
    /**
     * Send a text message to an international number without "+", e.g. 966512345678.
     */
    public function send(string $phone, string $message): void
    {
        match (config('services.sms.driver')) {
            'taqnyat' => $this->sendWithTaqnyat($phone, $message),
            'unifonic' => $this->sendWithUnifonic($phone, $message),
            default => Log::info('SMS to '.$phone.': '.$message),
        };
    }

    /**
     * Taqnyat (taqnyat.sa) - Saudi CST licensed SMS gateway.
     */
    private function sendWithTaqnyat(string $phone, string $message): void
    {
        Http::connectTimeout(5)
            ->timeout(10)
            ->withToken((string) config('services.sms.taqnyat.token'))
            ->post('https://api.taqnyat.sa/v1/messages', [
                'recipients' => [$phone],
                'body' => $message,
                'sender' => config('services.sms.taqnyat.sender'),
            ])
            ->throw();
    }

    /**
     * Unifonic (unifonic.com) - Saudi CST licensed SMS gateway.
     */
    private function sendWithUnifonic(string $phone, string $message): void
    {
        $response = Http::connectTimeout(5)
            ->timeout(10)
            ->asForm()
            ->post('https://el.cloud.unifonic.com/rest/SMS/messages', [
                'AppSid' => config('services.sms.unifonic.app_sid'),
                'SenderID' => config('services.sms.unifonic.sender'),
                'Recipient' => $phone,
                'Body' => $message,
            ])
            ->throw();

        if ($response->json('success') !== true && $response->json('success') !== 'true') {
            throw new RuntimeException('Unifonic SMS failed: '.$response->body());
        }
    }
}
