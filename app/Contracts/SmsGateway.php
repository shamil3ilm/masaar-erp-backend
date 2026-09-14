<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * A provider that delivers one text message.
 *
 * Implementations throw when the provider refuses or cannot be reached;
 * SmsService decides what a failure means for the caller.
 */
interface SmsGateway
{
    public function send(string $to, string $message): void;
}
