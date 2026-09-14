<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Contracts\SmsGateway;
use App\Services\Core\Sms\LogSmsGateway;
use App\Services\Core\SmsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * SMS delivery through the configured provider, faked at the HTTP boundary.
 */
class SmsGatewayTest extends TestCase
{
    public function test_log_driver_is_the_default(): void
    {
        config(['sms.driver' => 'log']);

        $this->assertInstanceOf(LogSmsGateway::class, app(SmsGateway::class));
    }

    public function test_an_unknown_driver_fails_loudly(): void
    {
        config(['sms.driver' => 'carrier-pigeon']);

        $this->expectException(InvalidArgumentException::class);

        app(SmsGateway::class);
    }

    public function test_twilio_receives_the_message(): void
    {
        config(['sms.driver' => 'twilio', 'sms.twilio' => ['sid' => 'AC123', 'token' => 'secret', 'from' => '+15550000000']]);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

        app(SmsGateway::class)->send('+966500000001', 'Your code is 1234');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('AC123:secret'))
            && $request['To'] === '+966500000001'
            && $request['Body'] === 'Your code is 1234');
    }

    public function test_vonage_receives_the_message(): void
    {
        config(['sms.driver' => 'vonage', 'sms.vonage' => ['api_key' => 'k', 'api_secret' => 's', 'from' => 'ERP']]);
        Http::fake(['rest.nexmo.com/*' => Http::response(['messages' => [['status' => '0']]])]);

        app(SmsGateway::class)->send('+966500000001', 'Hello');

        Http::assertSent(fn (Request $request) => $request['to'] === '+966500000001' && $request['text'] === 'Hello');
    }

    /**
     * Vonage reports a refused message with HTTP 200.
     */
    public function test_a_vonage_refusal_is_an_error(): void
    {
        config(['sms.driver' => 'vonage', 'sms.vonage' => ['api_key' => 'k', 'api_secret' => 's', 'from' => 'ERP']]);
        Http::fake(['rest.nexmo.com/*' => Http::response(['messages' => [['status' => '4', 'error-text' => 'Bad Credentials']]])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bad Credentials');

        app(SmsGateway::class)->send('+966500000001', 'Hello');
    }

    /**
     * Delivery is best effort for callers, and the log does not carry the
     * full phone number.
     */
    public function test_the_service_logs_failures_quietly(): void
    {
        config(['sms.driver' => 'twilio', 'sms.twilio' => ['sid' => 'AC123', 'token' => 'secret', 'from' => '+15550000000']]);
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'down'], 503)]);
        Log::spy();

        app(SmsService::class)->send('+966500000001', 'Hello');

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context) => $context['to'] === '*********0001'
        );
    }
}
