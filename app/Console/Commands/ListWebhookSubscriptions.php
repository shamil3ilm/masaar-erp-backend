<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Core\Webhook;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Lists webhook subscriptions and the model events each one would receive.
 *
 * Run this before enabling webhook emission on an environment to see who is
 * subscribed and what they would start receiving.
 */
class ListWebhookSubscriptions extends Command
{
    protected $signature = 'webhooks:subscriptions {--org= : Limit to one organization_id}';

    protected $description = 'Show webhook subscriptions and the model events they would receive';

    /** Models that emit webhooks via the DispatchesWebhooks trait. */
    private const EMITTING_MODELS = [
        \App\Models\Sales\Invoice::class,
        \App\Models\Sales\Contact::class,
        \App\Models\Sales\Quotation::class,
        \App\Models\Sales\SalesOrder::class,
        \App\Models\Sales\CreditNote::class,
        \App\Models\Sales\PaymentReceived::class,
        \App\Models\Purchase\PurchaseOrder::class,
        \App\Models\Purchase\Bill::class,
        \App\Models\HR\Employee::class,
        \App\Models\Manufacturing\WorkOrder::class,
    ];

    public function handle(): int
    {
        $this->line(config('webhooks.enabled', true)
            ? 'Emission is ENABLED (WEBHOOKS_ENABLED=true)'
            : 'Emission is DISABLED (WEBHOOKS_ENABLED=false)');

        $available = $this->emittableEvents();

        $webhooks = Webhook::withoutGlobalScopes()
            ->when($this->option('org'), fn ($q, $org) => $q->where('organization_id', $org))
            ->orderBy('organization_id')
            ->get();

        if ($webhooks->isEmpty()) {
            $this->info('No webhook subscriptions exist — enabling emission changes nothing.');

            return self::SUCCESS;
        }

        $rows = $webhooks->map(function (Webhook $webhook) use ($available) {
            $subscribed = $webhook->events ?? [];
            $matched    = in_array('*', $subscribed, true)
                ? $available
                : array_values(array_intersect($subscribed, $available));

            return [
                $webhook->organization_id,
                $webhook->name,
                $webhook->is_active ? 'active' : 'inactive',
                count($matched),
                implode(', ', array_slice($matched, 0, 4)) . (count($matched) > 4 ? ' …' : ''),
            ];
        });

        $this->table(['Org', 'Webhook', 'State', 'Events', 'Would receive'], $rows);

        $live = $webhooks->filter(fn (Webhook $w) => $w->is_active)->count();
        $this->line("{$live} active subscription(s) of {$webhooks->count()} total.");

        return self::SUCCESS;
    }

    /**
     * Every event the emitting models can produce, as `model.action`.
     *
     * @return list<string>
     */
    private function emittableEvents(): array
    {
        $events = [];

        foreach (self::EMITTING_MODELS as $model) {
            if (! in_array(DispatchesWebhooks::class, class_uses_recursive($model), true)) {
                continue;
            }

            $resource = Str::snake(class_basename($model));

            $property = (new ReflectionClass($model))->getStaticPropertyValue('webhookEvents', null)
                ?? ['created', 'updated', 'deleted'];

            foreach ($property as $action) {
                $events[] = "{$resource}.{$action}";
            }
        }

        return $events;
    }
}
