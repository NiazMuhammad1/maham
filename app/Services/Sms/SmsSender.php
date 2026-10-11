<?php

namespace App\Services\Sms;

interface SmsSender
{
    /**
     * Send a text message to an international number without "+", e.g. 966512345678.
     */
    public function send(string $recipient, string $message): void;
}
