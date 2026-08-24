<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Maintenance\EquipmentCounter;
use App\Models\Maintenance\CounterReading;
use App\Models\Maintenance\CounterBasedPlan;
use App\Models\Maintenance\CounterBasedOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CounterBasedMaintenanceService
{
    public function recordReading(EquipmentCounter $counter, float $value, Carbon $date, int $recordedBy): CounterReading
    {
        return DB::transaction(function () use ($counter, $value, $date, $recordedBy): CounterReading {
            $delta = $value - (float) $counter->current_reading;

            $reading = CounterReading::create([
                'uuid'          => Str::uuid(),
                'organization_id' => $counter->organization_id,
                'counter_id'    => $counter->id,
                'reading_value' => $value,
                'reading_date'  => $date,
                'delta_value'   => max(0, $delta),
                'recorded_by'   => $recordedBy,
            ]);

            $counter->update(['current_reading' => $value]);

            return $reading;
        });
    }

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

    public function generatePmOrder(CounterBasedPlan $plan): CounterBasedOrder
    {
        return DB::transaction(function () use ($plan): CounterBasedOrder {
            $order = CounterBasedOrder::create([
                'uuid'              => Str::uuid(),
                'organization_id'   => $plan->organization_id,
                'order_number'      => 'PMO-' . strtoupper(Str::random(8)),
                'maintenance_plan_id' => $plan->id,
                'floc_id'           => $plan->floc_id,
                'order_type'        => 'preventive',
                'description'       => 'Counter-based PM: ' . $plan->plan_number,
                'status'            => 'created',
                'priority'          => 'normal',
                'planned_start'     => now()->toDateString(),
                'counter_reading_at_trigger' => $plan->counter?->current_reading,
            ]);

            // Update plan's last/next readings
            if ($plan->counter_interval) {
                $plan->update([
                    'last_maintenance_reading' => $plan->counter?->current_reading,
                    'next_due_reading'         => (float) $plan->counter?->current_reading + (float) $plan->counter_interval,
                ]);
            }

            return $order;
        });
    }

    public function completePmOrder(CounterBasedOrder $order, array $data): void
    {
        $order->update([
            'status'      => 'completed',
            'actual_end'  => $data['actual_end'] ?? now()->toDateString(),
        ]);
    }
}
