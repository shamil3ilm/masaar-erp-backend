<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class SubscriptionController extends Controller
{
    public function __construct(private BillingService $billingService) {}

    public function current(): JsonResponse
    {
        $subscription = $this->billingService->currentSubscription(auth()->user()->organization_id);

        if (!$subscription) {
            return $this->success([
                'id' => 0,
                'plan_id' => 0,
                'status' => 'none',
                'starts_at' => null,
                'ends_at' => null,
                'base_price' => '0.00',
                'max_users' => 0,
                'max_branches' => 0,
                'organization_id' => auth()->user()->organization_id,
            ]);
        }

        return $this->success($subscription);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => ['required', 'integer', $this->offeredPlan()],
            'billing_cycle' => 'required|string|in:monthly,yearly,quarterly',
        ]);

        $subscription = $this->billingService->subscribeToPlan(
            auth()->user()->organization,
            $request->input('plan_id'),
            $request->all()
        );

        return $this->created($subscription->load('plan'));
    }

    public function changePlan(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => ['required', 'integer', $this->offeredPlan()],
        ]);

        $subscription = $this->billingService->activeSubscription(auth()->user()->organization_id);

        $updated = $this->billingService->changePlan($subscription, $request->input('plan_id'));
        return $this->success($updated->load('plan'));
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = $this->billingService->cancellableSubscription(auth()->user()->organization_id);

        if (!$subscription) {
            return $this->success(null, 'No active subscription to cancel');
        }

        $cancelled = $this->billingService->cancelSubscription($subscription, $request->input('reason', ''));
        return $this->success($cancelled);
    }

    public function availableAddons(): JsonResponse
    {
        return $this->success($this->billingService->activeAddons());
    }

    public function purchaseAddon(Request $request): JsonResponse
    {
        $request->validate([
            'addon_id' => ['required', 'integer', Rule::exists('subscription_addons', 'id')->where('is_active', true)],
            'quantity' => 'nullable|integer|min:1',
        ]);

        $subscription = $this->billingService->activeSubscription(auth()->user()->organization_id);

        $purchase = $this->billingService->purchaseAddon(
            $subscription,
            (int) $request->input('addon_id'),
            (int) $request->input('quantity', 1),
        );

        return $this->created($purchase);
    }

    /**
     * A plan the platform offers: active, public and not deleted. A tenant
     * cannot take a private or retired plan by naming its id.
     */
    private function offeredPlan(): Exists
    {
        return Rule::exists('subscription_plans', 'id')
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereNull('deleted_at');
    }
}
