<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('display_order')
            ->get();

        return $this->success($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $plan = SubscriptionPlan::create($this->validated($request));

        return $this->created($plan);
    }

    public function show(SubscriptionPlan $plan): JsonResponse
    {
        return $this->success($plan->load('meteredPricingTiers'));
    }

    public function update(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $plan->update($this->validated($request, $plan->id));

        return $this->success($plan->fresh());
    }

    public function destroy(SubscriptionPlan $plan): JsonResponse
    {
        $plan->delete();

        return $this->success(['message' => 'Plan deleted']);
    }

    /**
     * A plan's fields. Everything the table requires is required here, so an
     * empty body is refused rather than reaching the insert.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignore = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:30|unique:subscription_plans,code'.($ignore ? ','.$ignore : ''),
            'description' => 'nullable|string',
            'tier' => 'required|in:free,starter,professional,enterprise',
            'billing_cycle' => 'required|in:monthly,yearly,one_time',
            'base_price' => 'required|numeric|min:0',
            'currency_code' => 'nullable|string|size:3',
            'max_users' => 'nullable|integer|min:1',
            'max_branches' => 'nullable|integer|min:1',
            'storage_limit_mb' => 'nullable|integer|min:0',
            'max_invoices_per_month' => 'nullable|integer|min:0',
            'max_products' => 'nullable|integer|min:0',
            'max_customers' => 'nullable|integer|min:0',
            'max_employees' => 'nullable|integer|min:0',
            'api_calls_per_month' => 'nullable|integer|min:0',
            'included_modules' => 'required|array',
            'included_modules.*' => 'string',
            'features' => 'nullable|array',
            'trial_days' => 'nullable|integer|min:0',
            'trial_requires_card' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
            'is_popular' => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
