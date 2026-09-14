<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * zatca:setup subscribes the ERP to Masaar's invoice events.
 *
 * Masaar takes the callback as url, makes the signing secret itself and shows
 * it once, so the command sends url and prints the secret it gets back.
 */
class ZatcaSetupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'zatca-integration.enabled' => true,
            'zatca-integration.url' => 'https://compliance.test/api/v1',
            'zatca-integration.api_key' => 'test-api-key',
            'zatca-integration.webhook_secret' => '',
            'zatca-integration.retry.times' => 1,
        ]);
    }

    public function test_it_replaces_the_old_subscription_and_prints_the_new_secret(): void
    {
        $callback = url('/api/v1/webhooks/zatca');

        Http::fake([
            'compliance.test/api/v1/health' => Http::response(['status' => 'ok']),
            'compliance.test/api/v1/webhooks/old-id' => Http::response(['success' => true, 'message' => 'Webhook deleted']),
            'compliance.test/api/v1/webhooks' => fn (Request $request) => $request->method() === 'GET'
                ? Http::response(['success' => true, 'data' => ['webhooks' => [
                    ['id' => 'old-id', 'url' => $callback, 'is_active' => false],
                    ['id' => 'other-id', 'url' => 'https://elsewhere.test/hook', 'is_active' => true],
                ]]])
                : Http::response(['success' => true, 'data' => ['webhook' => [
                    'id' => 'new-id',
                    'url' => $callback,
                    'secret' => 'masaar-made-secret',
                    'is_active' => true,
                ]]], 201),
        ]);

        $this->artisan('zatca:setup')
            ->expectsOutputToContain('ZATCA_INTEGRATION_WEBHOOK_SECRET=masaar-made-secret')
            ->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/webhooks/old-id'));
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/webhooks/other-id'));
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r['url'] === $callback
            && !isset($r['callback_url'])
            && !isset($r['secret']));
    }

    public function test_masaars_refusal_is_reported_and_fails_the_command(): void
    {
        Http::fake([
            'compliance.test/api/v1/health' => Http::response(['status' => 'ok']),
            'compliance.test/api/v1/webhooks' => fn (Request $request) => $request->method() === 'GET'
                ? Http::response(['success' => true, 'data' => ['webhooks' => []]])
                : Http::response(['success' => false, 'error' => ['message' => 'The url field is required.', 'code' => 'VALIDATION_ERROR']], 422),
        ]);

        $this->artisan('zatca:setup')
            ->expectsOutputToContain('The url field is required.')
            ->assertFailed();
    }

    public function test_a_configured_secret_is_not_replaced_without_confirmation(): void
    {
        config(['zatca-integration.webhook_secret' => 'already-set']);
        Http::fake();

        $this->artisan('zatca:setup')
            ->expectsConfirmation('A webhook secret is already set. Replace the subscription and its secret?', 'no')
            ->assertSuccessful();

        Http::assertNothingSent();
    }
}
