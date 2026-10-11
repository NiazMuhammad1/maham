<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

/**
 * Taqnyat (taqnyat.sa) - Saudi CST licensed SMS gateway.
 */
class TaqnyatSmsSender implements SmsSender
{
    public function __construct(private string $token, private string $sender) {}

    public function send(string $recipient, string $message): void
    {
        Http::connectTimeout(5)
            ->timeout(10)
            ->withToken($this->token)
            ->post('https://api.taqnyat.sa/v1/messages', [
                'recipients' => [$recipient],
                'body' => $message,
                'sender' => $this->sender,
            ])
            ->throw();
    }
}
