<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsProvider implements SmsProviderInterface
{
    public function send(string $recipient, string $message): bool
    {
        Log::info(sprintf('SMS envoyé à %s (driver de développement) : %s', $recipient, $message));

        return true;
    }
}
