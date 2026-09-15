<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Sales\BillingPlan;
use App\Models\Sales\BillingPlanItem;
use App\Models\Sales\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BillingPlanService
{
    public function list(int $orgId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = BillingPlan::forOrganization($orgId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['plan_type'])) {
            $query->where('plan_type', $filters['plan_type']);
        }
        if (!empty($filters['sales_order_id'])) {
            $query->where('sales_order_id', $filters['sales_order_id']);
        }

        return $query->with(['salesOrder', 'quotation'])->latest()->paginate($perPage);
    }

    /**
     * Create a billing plan. A periodic plan asked to auto_generate_items gets
     * one pending item per interval between its start and end dates; the flag
     * itself is not a plan column.
     *
     * @param  array<string, mixed>  $data  validated plan fields with organization_id
     */
    public function create(array $data): BillingPlan
    {
        return DB::transaction(function () use ($data): BillingPlan {
            $plan = BillingPlan::create(Arr::except($data, ['auto_generate_items']));

            if ($plan->plan_type === BillingPlan::TYPE_PERIODIC && !empty($data['auto_generate_items'])) {
                $this->generatePeriodicItems($plan);
            }

            return $plan->load(['salesOrder', 'quotation', 'items']);
        });
    }

    /**
     * A billing plan of the current organization.
     *
     * @throws ModelNotFoundException
     */
    public function planOf(int $id): BillingPlan
    {
        return BillingPlan::findOrFail($id);
    }

    /**
     * A billing plan with its order, quotation and items, each item's invoice
     * embedded by its reference columns only.
     *
     * @throws ModelNotFoundException
     */
    public function planDetails(int $id): BillingPlan
    {
        return BillingPlan::with(['salesOrder', 'quotation', 'items.invoice:'.implode(',', Invoice::REFERENCE_COLUMNS)])
            ->findOrFail($id);
    }

    public function update(BillingPlan $plan, array $data): BillingPlan
    {
        $plan->update($data);
        return $plan->fresh(['salesOrder', 'quotation', 'items']);
    }

    public function delete(BillingPlan $plan): void
    {
        $plan->delete();
    }

    public function addItem(BillingPlan $plan, array $data): BillingPlanItem
    {
        $data['billing_plan_id'] = $plan->id;
        $data['organization_id'] = $plan->organization_id;

        return DB::transaction(function () use ($plan, $data): BillingPlanItem {
            $item = BillingPlanItem::create($data);
            $this->recalculateBilledValue($plan);
            return $item;
        });
    }

    /**
     * An item of the given plan, within the current organization.
     *
     * @throws ModelNotFoundException
     */
    public function itemOf(int $planId, int $itemId): BillingPlanItem
    {
        return BillingPlanItem::where('billing_plan_id', $planId)->findOrFail($itemId);
    }

    /**
     * Update an item and the plan's billed value in one transaction.
     */
    public function updateItem(BillingPlanItem $item, array $data): BillingPlanItem
    {
        return DB::transaction(function () use ($item, $data): BillingPlanItem {
            $item->update($data);
            $this->recalculateBilledValue($item->billingPlan()->firstOrFail());

            return $item->fresh();
        });
    }

    public function generatePeriodicItems(BillingPlan $plan): void
    {
        if ($plan->plan_type !== BillingPlan::TYPE_PERIODIC) {
            return;
        }
        if (empty($plan->start_date) || empty($plan->end_date) || empty($plan->periodic_interval_days)) {
            return;
        }

        $current = Carbon::parse($plan->start_date);
        $end = Carbon::parse($plan->end_date);
        $intervalDays = $plan->periodic_interval_days;
        $sortOrder = 0;

        DB::transaction(function () use ($plan, $current, $end, $intervalDays, &$sortOrder): void {
            while ($current->lte($end)) {
                BillingPlanItem::create([
                    'organization_id' => $plan->organization_id,
                    'billing_plan_id' => $plan->id,
                    'billing_date' => $current->toDateString(),
                    'billing_amount' => $plan->total_value,
                    'status' => BillingPlanItem::STATUS_PENDING,
                    'sort_order' => $sortOrder++,
                ]);
                $current->addDays($intervalDays);
            }
        });
    }

    /**
     * Bill a pending item against an invoice, update the plan's billed value
     * and complete the plan once nothing is pending.
     *
     * The plan and the item are re-read under a lock and the item's status is
     * checked there, so an item is billed once even when two requests race.
     *
     * @throws BusinessRuleException when the item is no longer pending
     */
    public function billItem(BillingPlanItem $item, int $invoiceId): BillingPlanItem
    {
        return DB::transaction(function () use ($item, $invoiceId): BillingPlanItem {
            $plan = BillingPlan::query()->lockForUpdate()->findOrFail($item->billing_plan_id);
            $item = BillingPlanItem::query()->lockForUpdate()->findOrFail($item->id);

            if ($item->status !== BillingPlanItem::STATUS_PENDING) {
                throw new BusinessRuleException('Only pending items can be billed.', 'INVALID_STATUS');
            }

            $item->update([
                'status' => BillingPlanItem::STATUS_BILLED,
                'invoice_id' => $invoiceId,
                'billed_at' => now(),
            ]);

            $this->recalculateBilledValue($plan);

            if (! $plan->items()->where('status', BillingPlanItem::STATUS_PENDING)->exists()) {
                $plan->update(['status' => BillingPlan::STATUS_COMPLETED]);
            }

            return $item->fresh(['invoice:'.implode(',', Invoice::REFERENCE_COLUMNS)]);
        });
    }

    public function getDueItems(int $orgId): Collection
    {
        return BillingPlanItem::forOrganization($orgId)
            ->where('status', BillingPlanItem::STATUS_PENDING)
            ->where('billing_date', '<=', Carbon::today())
            ->with(['billingPlan'])
            ->get();
    }

    private function recalculateBilledValue(BillingPlan $plan): void
    {
        $billedValue = $plan->items()
            ->where('status', BillingPlanItem::STATUS_BILLED)
            ->sum('billing_amount');

        $plan->update(['billed_value' => $billedValue]);
    }
}
