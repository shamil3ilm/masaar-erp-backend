<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Sales\BillingPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BillingPlanController extends Controller
{
    use ReportsBusinessRules, ValidatesOwnedRows;

    public function __construct(
        private BillingPlanService $billingPlanService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $plans = $this->billingPlanService->list(
            $request->user()->organization_id,
            $request->only(['status', 'plan_type', 'sales_order_id']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sales_order_id' => ['nullable', $this->ownedBy('sales_orders')],
            'quotation_id' => ['nullable', $this->ownedBy('quotations')],
            'plan_type' => 'required|in:milestone,periodic',
            'billing_currency' => 'nullable|string|size:3',
            'total_value' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'periodic_interval_days' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:5000',
            'auto_generate_items' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $plan = $this->billingPlanService->create(array_merge(
            $validator->validated(),
            ['organization_id' => $request->user()->organization_id]
        ));

        return $this->created($plan);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->billingPlanService->planDetails($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = $this->billingPlanService->planOf($id);

        $validator = Validator::make($request->all(), [
            'plan_type' => 'nullable|in:milestone,periodic',
            'billing_currency' => 'nullable|string|size:3',
            'total_value' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:draft,active,completed,cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'periodic_interval_days' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->billingPlanService->update($plan, $validator->validated());

        return $this->success($updated);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->billingPlanService->delete($this->billingPlanService->planOf($id));

        return $this->noContent();
    }

    public function addItem(Request $request, int $id): JsonResponse
    {
        $plan = $this->billingPlanService->planOf($id);

        $validator = Validator::make($request->all(), [
            'milestone_description' => 'nullable|string|max:255',
            'billing_date' => 'required|date',
            'billing_percent' => 'nullable|numeric|min:0|max:100',
            'billing_amount' => 'required|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $item = $this->billingPlanService->addItem($plan, $validator->validated());

        return $this->created($item);
    }

    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $item = $this->billingPlanService->itemOf($id, $itemId);

        $validator = Validator::make($request->all(), [
            'milestone_description' => 'nullable|string|max:255',
            'billing_date' => 'nullable|date',
            'billing_percent' => 'nullable|numeric|min:0|max:100',
            'billing_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:pending,billed,cancelled',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->billingPlanService->updateItem($item, $validator->validated());

        return $this->success($updated);
    }

    public function billItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $item = $this->billingPlanService->itemOf($id, $itemId);

        $validator = Validator::make($request->all(), [
            'invoice_id' => ['required', $this->ownedBy('invoices')],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        try {
            $updated = $this->billingPlanService->billItem($item, (int) $request->input('invoice_id'));
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($updated, 'Item billed successfully.');
    }

    public function dueItems(Request $request): JsonResponse
    {
        $items = $this->billingPlanService->getDueItems($request->user()->organization_id);

        return $this->success($items);
    }
}
