<?php

declare(strict_types=1);

namespace App\Services\Core\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class VonageSmsGateway implements SmsGateway
{
    private const URL = 'https://rest.nexmo.com/sms/json';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $from,
    ) {}

    public function send(string $to, string $message): void
    {
        $response = Http::asForm()
            ->timeout(10)
            ->post(self::URL, [
                'api_key'    => $this->apiKey,
                'api_secret' => $this->apiSecret,
                'to'         => $to,
                'from'       => $this->from,
                'text'       => $message,
            ])
            ->throw();

        // Vonage answers 200 for a refused message; the per-message status
        // says whether it was accepted.
        $status = (string) $response->json('messages.0.status', '-1');

        if ($status !== '0') {
            throw new RuntimeException(
                'Vonage refused the message: ' . ($response->json('messages.0.error-text') ?? "status {$status}")
            );
        }
    }
}
