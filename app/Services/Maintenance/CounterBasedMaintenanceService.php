<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Maintenance\CounterBasedOrder;
use App\Models\Maintenance\CounterBasedPlan;
use App\Models\Maintenance\CounterReading;
use App\Models\Maintenance\EquipmentCounter;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Counter-based maintenance: equipment counters and their readings, plans due
 * at a counter reading, and the orders generated from them.
 *
 * A reading, an order generation and a completion each run on the locked
 * counter, plan or order, so concurrent requests work from the latest values
 * instead of overwriting each other.
 */
class CounterBasedMaintenanceService
{
    private const PAGE_SIZE = 20;

    /** Order statuses after which an order cannot be completed. */
    private const FINISHED_STATUSES = ['completed', 'closed', 'cancelled'];

    public function paginateCounters(int $organizationId): LengthAwarePaginator
    {
        return EquipmentCounter::where('organization_id', $organizationId)
            ->with(['equipment', 'functionalLocation'])
            ->paginate(self::PAGE_SIZE);
    }

    public function createCounter(int $organizationId, array $data): EquipmentCounter
    {
        return EquipmentCounter::create([
            ...$data,
            'organization_id' => $organizationId,
            'uuid' => (string) Str::uuid(),
        ]);
    }

    /**
     * The organization's counter; another organization's id is not found.
     */
    public function findCounterOrFail(int $organizationId, int $counterId): EquipmentCounter
    {
        return EquipmentCounter::where('organization_id', $organizationId)->findOrFail($counterId);
    }

    /**
     * Record a reading and move the counter to it. The delta is measured from
     * the counter's latest reading and is never negative.
     */
    public function recordReading(EquipmentCounter $counter, float $value, Carbon $date, int $recordedBy): CounterReading
    {
        return $counter->lockForTransition(function (EquipmentCounter $counter) use ($value, $date, $recordedBy): CounterReading {
            $delta = $value - (float) $counter->current_reading;

            $reading = CounterReading::create([
                'uuid' => Str::uuid(),
                'organization_id' => $counter->organization_id,
                'counter_id' => $counter->id,
                'reading_value' => $value,
                'reading_date' => $date,
                'delta_value' => max(0, $delta),
                'recorded_by' => $recordedBy,
            ]);

            $counter->update(['current_reading' => $value]);

            return $reading;
        });
    }

    public function paginatePlans(int $organizationId): LengthAwarePaginator
    {
        return CounterBasedPlan::where('organization_id', $organizationId)
            ->with(['functionalLocation', 'counter', 'taskList'])
            ->paginate(self::PAGE_SIZE);
    }

    public function createPlan(int $organizationId, array $data): CounterBasedPlan
    {
        return CounterBasedPlan::create([
            ...$data,
            'organization_id' => $organizationId,
            'uuid' => (string) Str::uuid(),
        ]);
    }

    /**
     * The organization's plan; another organization's id is not found.
     */
    public function findPlanOrFail(int $organizationId, int $planId): CounterBasedPlan
    {
        return CounterBasedPlan::where('organization_id', $organizationId)->findOrFail($planId);
    }

    /**
     * Active counter-based plans whose counter has reached the next due reading.
     *
     * @return list<CounterBasedPlan>
     */
    public function checkDueOrders(int $orgId): array
    {
        $plans = CounterBasedPlan::where('organization_id', $orgId)
            ->where('plan_type', 'counter_based')
            ->where('active', true)
            ->whereNotNull('next_due_reading')
            ->with(['counter', 'functionalLocation'])
            ->get();

        $due = [];
        foreach ($plans as $plan) {
            if ($plan->counter && (float) $plan->counter->current_reading >= (float) $plan->next_due_reading) {
                $due[] = $plan;
            }
        }

        return $due;
    }

    /**
     * Generate an order from the plan and move the plan's due reading one
     * interval past the counter's current reading.
     */
    public function generatePmOrder(CounterBasedPlan $plan): CounterBasedOrder
    {
        return $plan->lockForTransition(function (CounterBasedPlan $plan): CounterBasedOrder {
            $currentReading = $plan->counter?->current_reading;

            $order = CounterBasedOrder::create([
                'uuid' => Str::uuid(),
                'organization_id' => $plan->organization_id,
                'order_number' => 'PMO-'.strtoupper(Str::random(8)),
                'maintenance_plan_id' => $plan->id,
                'floc_id' => $plan->floc_id,
                'order_type' => 'preventive',
                'description' => 'Counter-based PM: '.$plan->plan_number,
                'status' => 'created',
                'priority' => 'normal',
                'planned_start' => now()->toDateString(),
                'counter_reading_at_trigger' => $currentReading,
            ]);

            if ($plan->counter_interval) {
                $plan->update([
                    'last_maintenance_reading' => $currentReading,
                    'next_due_reading' => (float) $currentReading + (float) $plan->counter_interval,
                ]);
            }

            return $order;
        });
    }

    public function paginateOrders(int $organizationId): LengthAwarePaginator
    {
        return CounterBasedOrder::where('organization_id', $organizationId)
            ->with(['maintenancePlan', 'functionalLocation'])
            ->orderBy('created_at', 'desc')
            ->paginate(self::PAGE_SIZE);
    }

    /**
     * The organization's order; another organization's id is not found.
     */
    public function findOrderOrFail(int $organizationId, int $orderId): CounterBasedOrder
    {
        return CounterBasedOrder::where('organization_id', $organizationId)->findOrFail($orderId);
    }

    /**
     * Complete an order that is not already completed, closed or cancelled.
     *
     * @throws InvalidArgumentException when the order is already finished
     */
    public function completePmOrder(CounterBasedOrder $order, array $data): CounterBasedOrder
    {
        return $order->lockForTransition(function (CounterBasedOrder $order) use ($data): CounterBasedOrder {
            if (in_array($order->status, self::FINISHED_STATUSES, true)) {
                throw new InvalidArgumentException("A counter-based order with status '{$order->status}' cannot be completed.");
            }

            $order->update([
                'status' => 'completed',
                'actual_end' => $data['actual_end'] ?? now()->toDateString(),
            ]);

            return $order->fresh();
        });
    }
}
