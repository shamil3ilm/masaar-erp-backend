<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Compliance\MasaarClient;
use Illuminate\Console\Command;

class SetupZatcaIntegration extends Command
{
    protected $signature = 'zatca:setup';

    protected $description = 'Check the compliance integration and subscribe to its invoice events';

    private const EVENTS = ['invoice.cleared', 'invoice.reported', 'invoice.rejected', 'invoice.issued'];

    public function handle(MasaarClient $client): int
    {
        if (!(bool) config('zatca-integration.enabled', true)) {
            $this->info('ZATCA integration is disabled');

            return self::SUCCESS;
        }

        $url = (string) config('zatca-integration.url', '');
        $apiKey = (string) config('zatca-integration.api_key', '');
        $apiSecret = (string) config('zatca-integration.api_secret', '');

        if ($url === '' || $apiKey === '' || $apiSecret === '') {
            $this->error('Set ZATCA_INTEGRATION_URL, ZATCA_INTEGRATION_API_KEY and ZATCA_INTEGRATION_API_SECRET first.');

            return self::FAILURE;
        }

        // Replacing the subscription replaces its secret, which stops webhooks
        // being accepted until the new one is configured.
        if ((string) config('zatca-integration.webhook_secret', '') !== ''
            && !$this->confirm('A webhook secret is already set. Replace the subscription and its secret?', false)) {
            $this->info('Left the existing subscription as it is.');

            return self::SUCCESS;
        }

        try {
            $status = $client->checkHealth();
            $connectivity = $status === 200 ? 'OK' : 'FAILED (HTTP ' . $status . ')';
        } catch (\Throwable $e) {
            $connectivity = 'FAILED (' . $e->getMessage() . ')';
        }

        $callbackUrl = url('/api/v1/webhooks/zatca');
        $rows = [
            ['URL', $url],
            ['API Key', substr($apiKey, 0, 8) . '****'],
            ['Connectivity', $connectivity],
            ['Callback URL', $callbackUrl],
        ];

        try {
            $webhook = $client->registerWebhook($callbackUrl, self::EVENTS);
        } catch (\Throwable $e) {
            $this->table(['Setting', 'Value'], [...$rows, ['Webhook', 'FAILED']]);
            $this->error('Webhook registration failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Setting', 'Value'], [...$rows, ['Webhook', 'Registered (' . $webhook['id'] . ')']]);

        $this->newLine();
        $this->warn('Masaar shows this secret only once. Set it in this environment and clear the config cache:');
        $this->line('  ZATCA_INTEGRATION_WEBHOOK_SECRET=' . $webhook['secret']);
        $this->line('  php artisan config:clear');
        $this->line('Until then every webhook from Masaar is refused.');

        return self::SUCCESS;
    }
}
