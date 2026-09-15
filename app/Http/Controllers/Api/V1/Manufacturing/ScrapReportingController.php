<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\ScrapReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScrapReportingController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly ScrapReportingService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'work_order_id',
            'product_id',
            'scrap_cause',
            'gl_posted',
            'from_date',
            'to_date',
        ]);

        $reports = $this->service->list($filters);

        return $this->success($reports, 'Scrap reports retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'work_order_id' => ['nullable', $this->ownedBy('work_orders')],
            'product_id' => ['required', $this->ownedBy('products')],
            'warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
            'scrap_date' => 'required|date',
            'scrap_quantity' => 'required|numeric|min:0.0001',
            'unit_of_measure' => 'nullable|string|max:20',
            'scrap_cause' => 'nullable|in:defect,damage,obsolete,process_loss,machine_failure,other',
            'scrap_code' => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'estimated_value' => 'nullable|numeric|min:0',
            'is_recoverable' => 'boolean',
            'recovery_value' => 'nullable|numeric|min:0',
            'reported_by' => ['nullable', $this->ownedBy('users')],
        ]);

        $validated['reported_by'] = $validated['reported_by'] ?? auth()->id();

        $report = $this->service->create($validated);

        return $this->created($report->load(['product', 'workOrder', 'warehouse', 'reportedBy']), 'Scrap report created successfully.');
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->service->findOrFail($id, ['product', 'workOrder', 'warehouse', 'reportedBy']));
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $report = $this->service->findOrFail($id);

        $validated = $request->validate([
            'work_order_id' => ['nullable', $this->ownedBy('work_orders')],
            'product_id' => ['sometimes', $this->ownedBy('products')],
            'warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
            'scrap_date' => 'sometimes|date',
            'scrap_quantity' => 'sometimes|numeric|min:0.0001',
            'unit_of_measure' => 'nullable|string|max:20',
            'scrap_cause' => 'nullable|in:defect,damage,obsolete,process_loss,machine_failure,other',
            'scrap_code' => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'estimated_value' => 'nullable|numeric|min:0',
            'is_recoverable' => 'boolean',
            'recovery_value' => 'nullable|numeric|min:0',
        ]);

        return $this->success($this->service->update($report, $validated), 'Scrap report updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->delete($this->service->findOrFail($id));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->noContent();
    }

    public function postToGL(int $id): JsonResponse
    {
        $updated = $this->service->postToGL($this->service->findOrFail($id));

        return $this->success($updated, 'Scrap report posted to GL successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date', 'work_order_id']);
        $summary = $this->service->getScrapSummary($filters);

        return $this->success($summary, 'Scrap summary retrieved successfully.');
    }
}
