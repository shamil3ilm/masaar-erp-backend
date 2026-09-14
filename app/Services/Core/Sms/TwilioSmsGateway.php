<?php

declare(strict_types=1);

namespace App\Services\Core\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;

final class TwilioSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $sid,
        private readonly string $token,
        private readonly string $from,
    ) {}

    public function send(string $to, string $message): void
    {
        Http::asForm()
            ->withBasicAuth($this->sid, $this->token)
            ->timeout(10)
            ->post(
                'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($this->sid) . '/Messages.json',
                ['To' => $to, 'From' => $this->from, 'Body' => $message]
            )
            ->throw();
    }
}
