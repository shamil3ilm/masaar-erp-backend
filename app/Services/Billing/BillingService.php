<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Billing\BillingInvoice;
use App\Models\Billing\DiscountCode;
use App\Models\Billing\OrganizationSubscription;
use App\Models\Billing\SubscriptionAddon;
use App\Models\Billing\SubscriptionAddonPurchase;
use App\Models\Billing\SubscriptionPlan;
use App\Models\Billing\UsageAlert;
use App\Models\Billing\UsageMetric;
use App\Models\Billing\UsageSnapshot;
use App\Models\Core\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    public function getPlans(): mixed
    {
        return SubscriptionPlan::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('display_order')
            ->get();
    }

    public function createPlan(array $data): SubscriptionPlan
    {
        return SubscriptionPlan::create($data);
    }

    public function updatePlan(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        $plan->update($data);

        return $plan->fresh();
    }

    public function deletePlan(SubscriptionPlan $plan): void
    {
        $plan->delete();
    }

    /**
     * The organization's most recent subscription, whatever its status.
     */
    public function currentSubscription(int $organizationId): ?OrganizationSubscription
    {
        return OrganizationSubscription::where('organization_id', $organizationId)
            ->with('plan')
            ->latest()
            ->first();
    }

    public function activeSubscription(int $organizationId): OrganizationSubscription
    {
        return OrganizationSubscription::where('organization_id', $organizationId)
            ->where('status', OrganizationSubscription::STATUS_ACTIVE)
            ->firstOrFail();
    }

    /**
     * The organization's subscription that is active or in trial, if any.
     */
    public function cancellableSubscription(int $organizationId): ?OrganizationSubscription
    {
        return OrganizationSubscription::where('organization_id', $organizationId)
            ->whereIn('status', [OrganizationSubscription::STATUS_ACTIVE, OrganizationSubscription::STATUS_TRIAL])
            ->first();
    }

    /**
     * @return Collection<int, SubscriptionAddon>
     */
    public function activeAddons(): Collection
    {
        return SubscriptionAddon::where('is_active', true)->get();
    }

    public function purchaseAddon(OrganizationSubscription $subscription, int $addonId, int $quantity): SubscriptionAddonPurchase
    {
        $addon = SubscriptionAddon::where('is_active', true)->findOrFail($addonId);

        return $subscription->addonPurchases()->create([
            'addon_id' => $addon->id,
            'quantity' => $quantity,
            'unit_price' => $addon->price,
            'total_price' => $addon->price * $quantity,
            'starts_at' => now(),
            'status' => 'active',
        ]);
    }

    public function paginateInvoices(int $organizationId, int $perPage): LengthAwarePaginator
    {
        return BillingInvoice::where('organization_id', $organizationId)
            ->with('subscription.plan')
            ->orderByDesc('invoice_date')
            ->paginate($perPage);
    }

    /**
     * Records the invoice as paid in full, once, on the locked row.
     *
     * @throws BusinessRuleException when the invoice is already paid
     */
    public function markInvoicePaid(BillingInvoice $invoice): BillingInvoice
    {
        return DB::transaction(function () use ($invoice): BillingInvoice {
            $locked = BillingInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($locked->status === 'paid') {
                throw new BusinessRuleException('This invoice is already paid.', 'INVOICE_ALREADY_PAID', 422);
            }

            $locked->update([
                'status' => 'paid',
                'paid_at' => now(),
                'amount_paid' => $locked->total,
                'amount_due' => 0,
            ]);

            return $locked->fresh();
        });
    }

    public function paginateUsageHistory(int $organizationId, ?string $metricType, int $perPage): LengthAwarePaginator
    {
        return UsageMetric::where('organization_id', $organizationId)
            ->when($metricType, fn ($q, $type) => $q->where('metric_type', $type))
            ->orderByDesc('metric_date')
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, UsageAlert>
     */
    public function usageAlerts(int $organizationId, ?string $status): Collection
    {
        return UsageAlert::where('organization_id', $organizationId)
            ->when($status, fn ($q, $value) => $q->where('status', $value))
            ->orderByDesc('created_at')
            ->get();
    }

    public function subscribeToPlan(Organization $organization, int $planId, array $data = []): OrganizationSubscription
    {
        return DB::transaction(function () use ($organization, $planId, $data) {
            $plan = SubscriptionPlan::findOrFail($planId);

            $subscription = OrganizationSubscription::create([
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'status' => $plan->trial_days > 0 ? 'trial' : 'active',
                'starts_at' => now(),
                'ends_at' => $plan->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                'base_price' => $plan->base_price,
                'max_users' => $plan->max_users,
                'max_branches' => $plan->max_branches,
                'storage_limit_mb' => $plan->storage_limit_mb,
                'max_invoices_per_month' => $plan->max_invoices_per_month,
                'enabled_modules' => $plan->included_modules,
                'enabled_features' => $plan->features,
                'auto_renew' => $data['auto_renew'] ?? true,
                'next_billing_date' => $plan->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                'discount_code' => $data['discount_code'] ?? null,
            ]);

            return $subscription;
        });
    }

    public function cancelSubscription(OrganizationSubscription $subscription, string $reason): OrganizationSubscription
    {
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'auto_renew' => false,
        ]);

        return $subscription->fresh();
    }

    public function changePlan(OrganizationSubscription $subscription, int $newPlanId): OrganizationSubscription
    {
        return DB::transaction(function () use ($subscription, $newPlanId) {
            $newPlan = SubscriptionPlan::findOrFail($newPlanId);

            $subscription->update([
                'plan_id' => $newPlan->id,
                'base_price' => $newPlan->base_price,
                'max_users' => $newPlan->max_users,
                'max_branches' => $newPlan->max_branches,
                'storage_limit_mb' => $newPlan->storage_limit_mb,
                'max_invoices_per_month' => $newPlan->max_invoices_per_month,
                'enabled_modules' => $newPlan->included_modules,
                'enabled_features' => $newPlan->features,
            ]);

            return $subscription->fresh();
        });
    }

    public function recordUsage(int $organizationId, string $metricType, int $quantity): UsageMetric
    {
        return UsageMetric::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'metric_type' => $metricType,
                'metric_date' => now()->toDateString(),
            ],
            [
                'quantity' => DB::raw('quantity + ' . (int) $quantity),
                'billing_period' => now()->format('Y-m'),
            ]
        );
    }

    public function generateInvoice(OrganizationSubscription $subscription): BillingInvoice
    {
        return DB::transaction(function () use ($subscription) {
            $invoice = BillingInvoice::create([
                'invoice_number' => 'BILL-' . strtoupper(Str::random(8)),
                'organization_id' => $subscription->organization_id,
                'subscription_id' => $subscription->id,
                'billing_period_start' => now()->startOfMonth(),
                'billing_period_end' => now()->endOfMonth(),
                'invoice_date' => now(),
                'due_date' => now()->addDays(15),
                'currency_code' => 'USD',
                'subtotal' => $subscription->base_price,
                'discount_amount' => $subscription->discount_amount,
                'tax_amount' => 0,
                'total' => $subscription->base_price - $subscription->discount_amount,
                'amount_paid' => 0,
                'amount_due' => $subscription->base_price - $subscription->discount_amount,
                'status' => 'draft',
            ]);

            return $invoice;
        });
    }

    public function getUsageSummary(int $organizationId): ?UsageSnapshot
    {
        return UsageSnapshot::where('organization_id', $organizationId)->first();
    }

    public function validateDiscountCode(string $code, int $organizationId): ?DiscountCode
    {
        $discount = DiscountCode::where('code', $code)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->first();

        if (!$discount) {
            return null;
        }

        if ($discount->max_uses && $discount->times_used >= $discount->max_uses) {
            return null;
        }

        $orgUsage = $discount->usages()->where('organization_id', $organizationId)->count();
        if ($orgUsage >= $discount->max_uses_per_org) {
            return null;
        }

        return $discount;
    }
}
