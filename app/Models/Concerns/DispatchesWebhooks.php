<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Core\WebhookService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Emits a webhook when the model is created, updated, or deleted.
 *
 * Usage:
 * - Add `use DispatchesWebhooks;` to the model
 * - Optionally override $webhookEvents to change which events emit
 * - Optionally override toWebhookArray() to change the payload
 *
 * Nothing is sent unless the organization has an active subscription for the
 * event, and emission can be switched off entirely with WEBHOOKS_ENABLED=false.
 */
trait DispatchesWebhooks
{
    /**
     * Events that should trigger webhooks.
     * Override this property in your model to customize.
     */
    protected static array $webhookEvents = ['created', 'updated', 'deleted'];

    /**
     * Boot the trait.
     */
    protected static function bootDispatchesWebhooks(): void
    {
        foreach (static::$webhookEvents as $event) {
            static::$event(function ($model) use ($event) {
                static::dispatchWebhookForEvent($model, $event);
            });
        }
    }

    /**
     * Dispatch a webhook for a model event.
     *
     * A webhook is never worth failing the write for, so every failure here is
     * logged and swallowed.
     */
    protected static function dispatchWebhookForEvent($model, string $event): void
    {
        if (! static::webhookDispatchIsEnabled()) {
            return;
        }

        // Webhooks are scoped to an organization; a record without one has no
        // subscriber to notify.
        if (! isset($model->organization_id)) {
            return;
        }

        try {
            app(WebhookService::class)->dispatchForModel(
                $model,
                $event,
                $model->getAdditionalWebhookData($event)
            );
        } catch (\Throwable $e) {
            Log::warning("Failed to dispatch webhook: {$e->getMessage()}", [
                'model' => get_class($model),
                'id'    => $model->getKey(),
                'event' => $event,
            ]);
        }
    }

    /**
     * Whether model events should emit webhooks at all.
     *
     * WEBHOOKS_ENABLED is the kill switch: setting it to false stops emission
     * everywhere without a deploy. Tests opt in separately so the suite never
     * queues deliveries by accident.
     */
    protected static function webhookDispatchIsEnabled(): bool
    {
        if (! config('webhooks.enabled', true)) {
            return false;
        }

        if (app()->environment('testing')) {
            return (bool) config('webhooks.dispatch_in_tests', false);
        }

        return true;
    }

    /**
     * Get the event type for webhooks.
     * Override this to customize the event type format.
     */
    public function getWebhookEventType(string $action): string
    {
        $resourceType = Str::snake(class_basename($this));
        return "{$resourceType}.{$action}";
    }

    /**
     * Get the data to include in webhook payload.
     * Override this to customize the payload.
     */
    public function toWebhookArray(): array
    {
        return $this->makeHidden([
            'password',
            'secret',
            'api_key',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ])->toArray();
    }

    /**
     * Get additional data to include in webhook payload for specific events.
     * Override this to add event-specific data.
     */
    public function getAdditionalWebhookData(string $event): array
    {
        return [];
    }

    /**
     * Manually dispatch a webhook for this model.
     */
    public function dispatchWebhook(string $action, ?array $additionalData = null): int
    {
        if (!isset($this->organization_id)) {
            return 0;
        }

        $webhookService = app(WebhookService::class);

        return $webhookService->dispatchForModel(
            $this,
            $action,
            $additionalData
        );
    }
}
