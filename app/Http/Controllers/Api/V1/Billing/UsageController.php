<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function __construct(private BillingService $billingService) {}

    /**
     * Get current usage metrics for the organization.
     */
    public function index(): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $snapshot = $this->billingService->getUsageSummary($organizationId);

        if (!$snapshot) {
            // An organization not yet measured reads as using nothing.
            return $this->success([
                'organization_id' => $organizationId,
                'users_count' => 0,
                'branches_count' => 0,
                'storage_used_mb' => 0,
                'invoices_this_month' => 0,
                'products_count' => 0,
                'customers_count' => 0,
                'employees_count' => 0,
                'api_calls_this_month' => 0,
            ]);
        }

        return $this->success($snapshot);
    }

    public function summary(): JsonResponse
    {
        $snapshot = $this->billingService->getUsageSummary(auth()->user()->organization_id);
        return $this->success($snapshot);
    }

    public function history(Request $request): JsonResponse
    {
        return $this->paginated($this->billingService->paginateUsageHistory(
            auth()->user()->organization_id,
            $request->input('metric_type'),
            (int) $request->input('per_page', 30),
        ));
    }

    /**
     * Get usage alerts for the organization.
     */
    public function alerts(Request $request): JsonResponse
    {
        return $this->success($this->billingService->usageAlerts(
            auth()->user()->organization_id,
            $request->input('status'),
        ));
    }
}
