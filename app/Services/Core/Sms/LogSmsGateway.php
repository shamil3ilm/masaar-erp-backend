<?php

declare(strict_types=1);

namespace App\Services\Core\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Writes messages to the log instead of sending them. For local development.
 */
final class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS', ['to' => $to, 'message' => $message]);
    }
}
