<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Unifonic (unifonic.com) - Saudi CST licensed SMS gateway.
 */
class UnifonicSmsSender implements SmsSender
{
    public function __construct(private string $appSid, private string $sender) {}

    public function send(string $recipient, string $message): void
    {
        $response = Http::connectTimeout(5)
            ->timeout(10)
            ->asForm()
            ->post('https://el.cloud.unifonic.com/rest/SMS/messages', [
                'AppSid' => $this->appSid,
                'SenderID' => $this->sender,
                'Recipient' => $recipient,
                'Body' => $message,
            ])
            ->throw();

        if ($response->json('success') !== true && $response->json('success') !== 'true') {
            throw new RuntimeException('Unifonic SMS failed: '.$response->body());
        }
    }
}
