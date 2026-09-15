<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Jobs\DispatchWebhookJob;
use App\Models\Core\Webhook;
use App\Models\Core\WebhookDelivery;
use App\Models\Core\WebhookEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookService
{
    /** Host prefixes of loopback, private and link-local addresses a webhook may not target. */
    private const PRIVATE_HOST_PREFIXES = [
        'localhost', '127.', '10.', '172.16.', '172.17.', '172.18.', '172.19.', '172.20.', '172.21.', '172.22.',
        '172.23.', '172.24.', '172.25.', '172.26.', '172.27.', '172.28.', '172.29.', '172.30.', '172.31.',
        '192.168.', '0.', '::1', '169.254.',
    ];

    /**
     * Dispatch webhooks for an event.
     */
    public function dispatch(
        int $organizationId,
        string $eventType,
        array $data,
        ?string $resourceType = null,
        ?string $resourceId = null,
        bool $async = true
    ): int {
        // Find active webhooks subscribed to this event. This runs before the
        // event is recorded so organizations with no subscriptions do not
        // accumulate a WebhookEvent row for every model write.
        $webhooks = Webhook::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get()
            ->filter(fn ($webhook) => $webhook->subscribesTo($eventType));

        if ($webhooks->isEmpty()) {
            return 0;
        }

        $event = WebhookEvent::create([
            'organization_id' => $organizationId,
            'event_type' => $eventType,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'data' => $data,
            'webhooks_triggered' => $webhooks->count(),
        ]);

        // Create delivery records and dispatch
        foreach ($webhooks as $webhook) {
            $payload = $this->buildPayload($eventType, $data, $event->uuid);

            $delivery = WebhookDelivery::create([
                'webhook_id' => $webhook->id,
                'event_type' => $eventType,
                'payload' => $payload,
                'status' => WebhookDelivery::STATUS_PENDING,
            ]);

            if ($async) {
                DispatchWebhookJob::dispatch($delivery->id);
            } else {
                $this->sendWebhook($delivery);
            }
        }

        return $webhooks->count();
    }

    /**
     * Dispatch webhook for a model event.
     */
    public function dispatchForModel(
        Model $model,
        string $action,
        ?array $additionalData = null,
        bool $async = true
    ): int {
        $resourceType = class_basename($model);
        $eventType = Str::snake($resourceType) . '.' . $action;

        $data = array_merge(
            $this->transformModel($model),
            $additionalData ?? []
        );

        return $this->dispatch(
            $model->organization_id,
            $eventType,
            $data,
            $resourceType,
            (string) $model->getKey(),
            $async
        );
    }

    /**
     * Send a webhook delivery.
     */
    public function sendWebhook(WebhookDelivery $delivery): void
    {
        $webhook = $delivery->webhook;
        $payload = json_encode($delivery->payload);

        $headers = array_merge([
            'Content-Type' => $webhook->content_type,
            'X-Webhook-Id' => $webhook->uuid,
            'X-Webhook-Event' => $delivery->event_type,
            'X-Webhook-Delivery' => $delivery->uuid,
            'X-Webhook-Signature' => $webhook->generateSignature($payload),
            'X-Webhook-Timestamp' => (string) now()->timestamp,
        ], $webhook->headers ?? []);

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($headers)
                ->timeout($webhook->timeout_seconds)
                ->withBody($payload, $webhook->content_type)
                ->post($webhook->url);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $delivery->markAsSuccess(
                    $response->status(),
                    $response->body(),
                    $response->headers(),
                    $durationMs
                );
            } else {
                $delivery->markAsFailed(
                    "HTTP {$response->status()}: " . $response->reason(),
                    $response->status(),
                    $response->body(),
                    $durationMs
                );
            }
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $delivery->markAsFailed(
                $e->getMessage(),
                null,
                null,
                $durationMs
            );
        }
    }

    /**
     * Retry a failed delivery.
     */
    /**
     * Queues a failed delivery for another attempt. The delivery is re-read
     * under a lock so two retries of the same failure queue it once, and the
     * job is dispatched only after the new attempt is committed.
     */
    public function retryDelivery(WebhookDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery): void {
            $locked = WebhookDelivery::query()->lockForUpdate()->findOrFail($delivery->id);

            if (! $locked->shouldRetry()) {
                return;
            }

            $locked->update([
                'status' => WebhookDelivery::STATUS_PENDING,
                'attempt' => $locked->attempt + 1,
            ]);

            DispatchWebhookJob::dispatch($locked->id)->afterCommit();
        });
    }

    /**
     * A webhook of the organization by id; another organization's webhook is not found.
     */
    public function findForOrganization(int $organizationId, int $id): Webhook
    {
        return Webhook::where('organization_id', $organizationId)->findOrFail($id);
    }

    /**
     * Why a webhook may not deliver to this URL, or null when it may. A URL
     * must use HTTP or HTTPS, HTTPS in production, and must not name a private
     * or local host, which would let a tenant make the server call internal
     * services.
     *
     * @return array{message: string, code: string}|null
     */
    public function urlRefusal(string $url): ?array
    {
        $parsedUrl = parse_url($url);
        $scheme = $parsedUrl['scheme'] ?? '';

        if (! in_array($scheme, ['https', 'http'], true)) {
            return ['message' => 'Webhook URL must use HTTP or HTTPS scheme.', 'code' => 'INVALID_WEBHOOK_URL'];
        }

        if (App::isProduction() && $scheme !== 'https') {
            return ['message' => 'Webhook URL must use HTTPS in production.', 'code' => 'INVALID_WEBHOOK_URL'];
        }

        $host = $parsedUrl['host'] ?? '';

        foreach (self::PRIVATE_HOST_PREFIXES as $prefix) {
            if (str_starts_with($host, $prefix) || $host === $prefix) {
                return ['message' => 'Webhook URL cannot target private/local addresses.', 'code' => 'OPERATION_FAILED'];
            }
        }

        return null;
    }

    /**
     * Switches a webhook on or off, flipping the value stored now rather than
     * the one the caller loaded.
     */
    public function toggle(Webhook $webhook): Webhook
    {
        return DB::transaction(function () use ($webhook): Webhook {
            $locked = Webhook::query()->lockForUpdate()->findOrFail($webhook->id);
            $locked->update(['is_active' => ! $locked->is_active]);

            return $locked;
        });
    }

    /**
     * Retry all pending deliveries.
     */
    public function retryPendingDeliveries(): int
    {
        $deliveries = WebhookDelivery::where('status', WebhookDelivery::STATUS_PENDING)
            ->where('next_retry_at', '<=', now())
            ->limit(100)
            ->get();

        foreach ($deliveries as $delivery) {
            DispatchWebhookJob::dispatch($delivery->id);
        }

        return $deliveries->count();
    }

    /**
     * Create a webhook.
     */
    public function create(
        int $organizationId,
        User $user,
        string $name,
        string $url,
        array $events,
        array $options = []
    ): Webhook {
        return Webhook::create([
            'organization_id' => $organizationId,
            'created_by' => $user->id,
            'name' => $name,
            'url' => $url,
            'events' => $events,
            'headers' => $options['headers'] ?? null,
            'is_active' => $options['is_active'] ?? true,
            'retry_count' => $options['retry_count'] ?? 3,
            'timeout_seconds' => $options['timeout_seconds'] ?? 30,
            'content_type' => $options['content_type'] ?? 'application/json',
        ]);
    }

    /**
     * Update a webhook.
     */
    public function update(Webhook $webhook, array $data): Webhook
    {
        $webhook->update($data);
        return $webhook->fresh();
    }

    /**
     * Delete a webhook.
     */
    public function delete(Webhook $webhook): void
    {
        $webhook->delete();
    }

    /**
     * Test a webhook endpoint.
     */
    public function test(Webhook $webhook): array
    {
        $testPayload = $this->buildPayload('webhook.test', [
            'message' => 'This is a test webhook delivery',
            'webhook_id' => $webhook->uuid,
            'webhook_name' => $webhook->name,
            'test_timestamp' => now()->toIso8601String(),
        ], Str::uuid()->toString());

        $payload = json_encode($testPayload);

        $headers = array_merge([
            'Content-Type' => $webhook->content_type,
            'X-Webhook-Id' => $webhook->uuid,
            'X-Webhook-Event' => 'webhook.test',
            'X-Webhook-Delivery' => Str::uuid()->toString(),
            'X-Webhook-Signature' => $webhook->generateSignature($payload),
            'X-Webhook-Timestamp' => (string) now()->timestamp,
        ], $webhook->headers ?? []);

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($headers)
                ->timeout($webhook->timeout_seconds)
                ->withBody($payload, $webhook->content_type)
                ->post($webhook->url);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'duration_ms' => $durationMs,
                'response_body' => substr($response->body(), 0, 1000),
            ];
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ];
        }
    }

    /**
     * Get webhooks for organization.
     */
    public function getWebhooks(int $organizationId): Collection
    {
        return Webhook::where('organization_id', $organizationId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get delivery history for a webhook.
     */
    public function getDeliveryHistory(Webhook $webhook, int $limit = 50): Collection
    {
        return $webhook->deliveries()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get event history for organization.
     */
    public function getEventHistory(int $organizationId, int $limit = 50): Collection
    {
        return WebhookEvent::where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Cleanup old webhook events and deliveries.
     */
    public function cleanup(int $days = 30): int
    {
        $cutoff = now()->subDays($days);

        // Delete old events
        $eventsDeleted = WebhookEvent::where('created_at', '<', $cutoff)->delete();

        // Delete old successful deliveries
        $deliveriesDeleted = WebhookDelivery::where('created_at', '<', $cutoff)
            ->where('status', WebhookDelivery::STATUS_SUCCESS)
            ->delete();

        return $eventsDeleted + $deliveriesDeleted;
    }

    // ==================== Protected Methods ====================

    protected function buildPayload(string $eventType, array $data, string $eventId): array
    {
        return [
            'event' => $eventType,
            'event_id' => $eventId,
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ];
    }

    protected function transformModel(Model $model): array
    {
        // Get model attributes with any custom serialization
        if (method_exists($model, 'toWebhookArray')) {
            return $model->toWebhookArray();
        }

        // Default: use toArray but hide sensitive fields
        $data = $model->makeHidden(['password', 'secret', 'api_key', 'remember_token'])->toArray();

        return $data;
    }
}
