<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Sales\BackorderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BackorderController extends Controller
{
    use ReportsBusinessRules, ValidatesOwnedRows;

    public function __construct(
        private BackorderService $backorderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $records = $this->backorderService->list(
            $request->only(['status', 'product_id', 'sales_order_id', 'priority']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($records);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sales_order_id' => ['required', $this->ownedBy('sales_orders')],
            'sales_order_line_id' => ['nullable', $this->ownedThrough('sales_order_lines', 'sales_order_id', 'sales_orders')],
            'product_id' => ['required', $this->ownedBy('products')],
            'original_quantity' => 'required|numeric|min:0.0001',
            'backordered_quantity' => 'required|numeric|min:0.0001',
            'original_delivery_date' => 'nullable|date',
            'rescheduled_delivery_date' => 'nullable|date',
            'reason' => 'nullable|string|max:5000',
            'priority' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $record = $this->backorderService->create(array_merge(
            $validator->validated(),
            ['organization_id' => $request->user()->organization_id]
        ));

        return $this->created($record);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->backorderService->recordDetails($id));
    }

    public function reschedule(Request $request, int $id): JsonResponse
    {
        $record = $this->backorderService->recordOf($id);

        $validator = Validator::make($request->all(), [
            'rescheduled_delivery_date' => 'required|date',
            'reason' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->backorderService->reschedule(
            $record,
            $request->input('rescheduled_delivery_date'),
            $request->input('reason')
        );

        return $this->success($updated, 'Backorder rescheduled.');
    }

    public function fulfill(Request $request, int $id): JsonResponse
    {
        $record = $this->backorderService->recordOf($id);

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        try {
            $updated = $this->backorderService->fulfill($record, (float) $request->input('quantity'));
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($updated, 'Backorder fulfilled.');
    }

    public function cancel(int $id): JsonResponse
    {
        $updated = $this->backorderService->cancel($this->backorderService->recordOf($id));

        return $this->success($updated, 'Backorder cancelled.');
    }

    public function report(Request $request): JsonResponse
    {
        $report = $this->backorderService->getBackorderReport(
            $request->only(['from_date', 'to_date'])
        );

        return $this->success($report);
    }
}
