<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function __construct(
        private readonly SmsGateway $gateway,
    ) {}

    /**
     * Send an SMS message. Failures are logged but never rethrown: SMS delivery
     * is best-effort and must not abort the caller's workflow.
     */
    public function send(string $to, string $message): void
    {
        try {
            $this->gateway->send($to, $message);
        } catch (\Throwable $e) {
            Log::error('SMS send failed', [
                'gateway' => $this->gateway::class,
                'to'      => self::mask($to),
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * The last four digits, enough to tell recipients apart in a log.
     */
    private static function mask(string $phone): string
    {
        return str_repeat('*', max(0, strlen($phone) - 4)) . substr($phone, -4);
    }
}
